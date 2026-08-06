<?php

namespace Tests\Feature;

use App\Models\ZigoCommercialConceptRule;
use App\Services\Pricing\ZigoCommercialPricingEngine;
use Database\Seeders\ZigoUatCommercialConceptRulesSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

final class ZigoUatCommercialConceptRulesSeederTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite'); DB::reconnect('sqlite');
        Schema::create('zigo_commercial_concept_rules', function (Blueprint $t): void {
            $t->id(); $t->string('name'); $t->string('carrier'); $t->string('service');
            $t->string('segment'); $t->string('plan')->nullable(); $t->string('package_type');
            $t->string('concept'); $t->string('adjustment_type'); $t->decimal('value', 12, 4);
            $t->unsignedInteger('priority'); $t->dateTime('starts_at')->nullable();
            $t->dateTime('ends_at')->nullable(); $t->boolean('active'); $t->timestamps();
        });
    }

    public function test_first_run_creates_four_visible_rules_and_second_run_does_not_duplicate(): void
    {
        Log::spy();
        $this->seedRules();
        $this->assertSame(4, ZigoCommercialConceptRule::query()->count());
        $this->assertSame(
            ['area_extendida', 'kg_extra', 'otros', 'seguro'],
            ZigoCommercialConceptRule::query()->orderBy('concept')->pluck('concept')->all()
        );
        $area = ZigoCommercialConceptRule::query()->where('concept', 'area_extendida')->firstOrFail();
        $this->assertSame('porcentaje', $area->adjustment_type);
        $this->assertSame(10.0, (float) $area->value);
        $this->assertTrue($area->active);

        $this->seedRules();
        $this->assertSame(4, ZigoCommercialConceptRule::query()->count());
        Log::shouldHaveReceived('info')->times(8);

        $crmTemplate = (string) file_get_contents(resource_path('views/crm/pricing/tabs/profits.blade.php'));
        $this->assertStringContainsString('$conceptRules', $crmTemplate);
        $this->assertStringContainsString('Reglas comerciales por concepto', $crmTemplate);
    }

    public function test_engine_applies_seeded_rules_and_records_rule_ids_and_amounts(): void
    {
        $this->seedRules();
        $result = app(ZigoCommercialPricingEngine::class)->calculate(
            ['costo'=>0, 'costo_ae'=>190, 'costo_kgs_extras'=>36, 'costo_seguro'=>20, 'otros'=>10],
            ['carrier'=>'ESTAFETA'], 'terrestre', 'b2c', 'caja', .16
        );

        $this->assertSame(209.0, $result['commercial_breakdown']['area_extendida']);
        $this->assertSame(39.6, $result['commercial_breakdown']['kg_extra']);
        $this->assertSame(20.0, $result['commercial_breakdown']['seguro']);
        $this->assertSame(11.0, $result['commercial_breakdown']['otros']);
        $audit = $result['applied_rules']['area_extendida'];
        $this->assertNotNull($audit['rule_id']);
        $this->assertSame(190.0, $audit['operational_amount']);
        $this->assertSame(19.0, $audit['adjustment_amount']);
        $this->assertSame(209.0, $audit['commercial_amount']);
    }

    public function test_production_is_blocked_by_default(): void
    {
        app()->detectEnvironment(fn (): string => 'production');
        config()->set('zigo_devops.allow_uat_commercial_concept_rules_in_production', false);
        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('ZIGO_UAT_COMMERCIAL_CONCEPT_RULES_PRODUCTION_BLOCKED');
            $this->seedRules();
        } finally {
            app()->detectEnvironment(fn (): string => 'testing');
        }
    }

    private function seedRules(): void
    {
        app(ZigoUatCommercialConceptRulesSeeder::class)->run();
    }
}
