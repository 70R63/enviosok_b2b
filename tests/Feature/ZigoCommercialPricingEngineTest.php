<?php

namespace Tests\Feature;

use App\Models\ZigoCommercialConceptRule;
use App\Models\ZigoPricingRule;
use App\Services\Pricing\ZigoCommercialPricingEngine;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ZigoCommercialPricingEngineTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite'); DB::reconnect('sqlite');
        Schema::create('zigo_commercial_concept_rules', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('carrier'); $t->string('service'); $t->string('segment');
            $t->string('plan')->nullable(); $t->string('package_type'); $t->string('concept'); $t->string('adjustment_type');
            $t->decimal('value', 12, 4); $t->integer('priority'); $t->dateTime('starts_at')->nullable();
            $t->dateTime('ends_at')->nullable(); $t->boolean('active'); $t->timestamps();
        });
        Schema::create('zigo_pricing_rules', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('carrier'); $t->string('customer_segment'); $t->string('plan')->nullable();
            $t->string('package_type'); $t->decimal('margin_percentage', 8, 2); $t->decimal('fixed_fee', 10, 2);
            $t->decimal('min_price', 10, 2)->nullable(); $t->boolean('active'); $t->timestamps();
        });
        Schema::create('zigo_pricing_adjustments', function (Blueprint $t) { $t->id(); $t->string('carrier'); $t->boolean('active'); $t->string('customer_segment'); $t->string('package_type'); $t->string('adjustment_type'); $t->decimal('adjustment_value'); $t->integer('used_count')->default(0); $t->integer('max_uses')->nullable(); $t->dateTime('starts_at')->nullable(); $t->dateTime('ends_at')->nullable(); $t->string('name'); $t->timestamps(); });
        Schema::create('zigo_client_pricing_rules', function (Blueprint $t) { $t->id(); $t->integer('crm_client_id')->nullable(); $t->integer('api_client_id')->nullable(); $t->integer('user_id')->nullable(); $t->string('name'); $t->string('customer_segment')->nullable(); $t->string('package_type'); $t->string('discount_type'); $t->decimal('discount_value'); $t->integer('used_count')->default(0); $t->integer('max_uses')->nullable(); $t->dateTime('starts_at')->nullable(); $t->dateTime('ends_at')->nullable(); $t->boolean('active'); $t->timestamps(); });
    }

    /** @test */
    public function calculates_every_concept_then_vat_without_double_sum(): void
    {
        $this->rule('base', 'porcentaje', 20, 10);
        $this->rule('area_extendida', 'porcentaje', 10, 10);
        $this->rule('kg_extra', 'porcentaje', 50, 10);
        $this->rule('seguro', 'sin_margen', 0, 10);
        $this->rule('otros', 'monto_fijo', 3, 10);
        $result = $this->engine()->calculate([
            'costo' => 100, 'costo_ae' => 20, 'costo_kgs_extras' => 10,
            'costo_seguro' => 5, 'otros' => 2, 'total' => 158.92,
        ], ['carrier' => 'ESTAFETA'], 'terrestre', 'anonymous', 'caja', .16);
        $this->assertSame(['base'=>120.0,'area_extendida'=>22.0,'kg_extra'=>15.0,'seguro'=>5.0,'otros'=>5.0], $result['commercial_breakdown']);
        $this->assertSame(167.0, $result['commercial_subtotal']);
        $this->assertSame(26.72, $result['vat']);
        $this->assertSame(193.72, $result['customer_total']);
    }

    /** @test */
    public function priority_wins_and_expired_or_inactive_rules_do_not_apply(): void
    {
        $this->rule('base', 'porcentaje', 99, 50);
        $this->rule('base', 'monto_fijo', 7, 1);
        $this->rule('area_extendida', 'monto_fijo', 100, 1, false);
        $this->rule('kg_extra', 'monto_fijo', 100, 1, true, now()->subDays(2), now()->subDay());
        $result = $this->engine()->calculate(['costo'=>100,'costo_ae'=>10,'costo_kgs_extras'=>10], ['carrier'=>'ESTAFETA'], 'terrestre', 'b2c', 'caja', 0);
        $this->assertSame(107.0, $result['commercial_breakdown']['base']);
        $this->assertSame(10.0, $result['commercial_breakdown']['area_extendida']);
        $this->assertSame(10.0, $result['commercial_breakdown']['kg_extra']);
    }

    /** @test */
    public function falls_back_to_existing_base_rule_only_when_concept_rule_is_missing(): void
    {
        ZigoPricingRule::create(['name'=>'Base existente','carrier'=>'ESTAFETA','customer_segment'=>'anonymous','plan'=>null,'package_type'=>'caja','margin_percentage'=>25,'fixed_fee'=>5,'min_price'=>null,'active'=>true]);
        $result = $this->engine()->calculate(['costo'=>100,'costo_ae'=>20], ['carrier'=>'ESTAFETA'], 'diasig', 'anonymous', 'caja', .16);
        $this->assertSame(130.0, $result['commercial_breakdown']['base']);
        $this->assertSame(20.0, $result['commercial_breakdown']['area_extendida']);
        $this->assertSame(174.0, $result['customer_total']);
        $this->assertSame('legacy_base', $result['applied_rules']['base']['adjustment_type']);
        $this->assertSame(100.0, $result['applied_rules']['base']['operational_amount']);
        $this->assertSame(130.0, $result['applied_rules']['base']['commercial_amount']);
        $this->assertSame(30.0, $result['applied_rules']['base']['adjustment_amount']);
    }

    /** @test */
    public function matches_the_mandatory_uat_example_and_audits_each_rule(): void
    {
        $this->rule('base', 'monto_fijo', 64, 10);
        $this->rule('area_extendida', 'porcentaje', 10, 10);
        $this->rule('kg_extra', 'sin_margen', 0, 10);
        $result = $this->engine()->calculate(
            ['costo'=>120,'costo_ae'=>190,'costo_kgs_extras'=>36,'total'=>999],
            ['carrier'=>'ESTAFETA'], 'terrestre', 'b2c', 'caja', .16
        );
        $this->assertSame(['base'=>184.0,'area_extendida'=>209.0,'kg_extra'=>36.0,'seguro'=>0.0,'otros'=>0.0], $result['commercial_breakdown']);
        $this->assertSame(429.0, $result['commercial_subtotal']);
        $this->assertSame(68.64, $result['vat']);
        $this->assertSame(497.64, $result['customer_total']);
        $this->assertSame(['rule_id','rule_name','concept','adjustment_type','adjustment_value','operational_amount','commercial_amount','adjustment_amount'], array_keys($result['applied_rules']['area_extendida']));
    }

    /** @test */
    public function extended_area_percentage_above_ten_is_rejected_by_crm(): void
    {
        $request = Request::create('/crm/pricing/concept-rules', 'POST', [
            'name'=>'Área UAT','carrier'=>'ESTAFETA','service'=>'all','segment'=>'all',
            'plan'=>'','package_type'=>'all','concept'=>'area_extendida',
            'adjustment_type'=>'porcentaje','value'=>10.01,'priority'=>1,
        ]);
        try {
            app(\App\Http\Controllers\CRM\CrmPricingController::class)->storeConceptRule($request);
            $this->fail('El margen mayor a 10% debió rechazarse.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'El margen porcentual de área extendida no puede superar 10%.',
                $exception->errors()['value'][0]
            );
        }
    }

    private function rule(string $concept, string $type, float $value, int $priority, bool $active = true, $starts = null, $ends = null): void
    {
        ZigoCommercialConceptRule::create(['name'=>$concept,'carrier'=>'ESTAFETA','service'=>'all','segment'=>'all','plan'=>null,'package_type'=>'all','concept'=>$concept,'adjustment_type'=>$type,'value'=>$value,'priority'=>$priority,'starts_at'=>$starts,'ends_at'=>$ends,'active'=>$active]);
    }

    private function engine(): ZigoCommercialPricingEngine { return app(ZigoCommercialPricingEngine::class); }
}
