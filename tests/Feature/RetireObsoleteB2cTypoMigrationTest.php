<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RetireObsoleteB2cTypoMigrationTest extends TestCase
{
    private string $typoMigration;
    private string $canonicalMigration;

    protected function setUp(): void
    {
        parent::setUp();

        $this->typoMigration = database_path(
            'migrations/2026_05_31_212435_create_b2c_cotizacions_table.php'
        );
        $this->canonicalMigration = database_path(
            'migrations/2026_05_31_213836_create_b2c_cotizaciones_table.php'
        );
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('b2c_cotizacions');
        Schema::dropIfExists('b2c_cotizaciones');

        parent::tearDown();
    }

    public function test_typo_migration_is_a_non_destructive_no_op(): void
    {
        Schema::create('b2c_cotizaciones', function (Blueprint $table) {
            $table->id();
            $table->string('marker');
        });
        DB::table('b2c_cotizaciones')->insert(['marker' => 'preserved']);

        $migration = require $this->typoMigration;
        $migration->up();

        $this->assertFalse(Schema::hasTable('b2c_cotizacions'));
        $this->assertSame(
            'preserved',
            DB::table('b2c_cotizaciones')->value('marker')
        );

        $migration->down();

        $this->assertFalse(Schema::hasTable('b2c_cotizacions'));
        $this->assertTrue(Schema::hasTable('b2c_cotizaciones'));
        $this->assertSame(
            'preserved',
            DB::table('b2c_cotizaciones')->value('marker')
        );
    }

    public function test_fresh_sequence_creates_only_the_canonical_table_and_is_reversible(): void
    {
        $typo = require $this->typoMigration;
        $canonical = require $this->canonicalMigration;

        $typo->up();
        $canonical->up();

        $this->assertFalse(Schema::hasTable('b2c_cotizacions'));
        $this->assertTrue(Schema::hasTable('b2c_cotizaciones'));

        $canonical->down();
        $typo->down();

        $this->assertFalse(Schema::hasTable('b2c_cotizacions'));
        $this->assertFalse(Schema::hasTable('b2c_cotizaciones'));
    }

    public function test_typo_down_preserves_both_tables_when_they_already_exist(): void
    {
        foreach (['b2c_cotizacions', 'b2c_cotizaciones'] as $table) {
            Schema::create($table, function (Blueprint $blueprint) {
                $blueprint->id();
                $blueprint->string('marker');
            });
            DB::table($table)->insert(['marker' => 'historical-data']);
        }

        (require $this->typoMigration)->down();

        foreach (['b2c_cotizacions', 'b2c_cotizaciones'] as $table) {
            $this->assertTrue(Schema::hasTable($table));
            $this->assertSame(
                'historical-data',
                DB::table($table)->value('marker')
            );
        }
    }

    public function test_production_cleanup_keeps_the_legacy_data_safeguard(): void
    {
        Schema::create('b2c_cotizacions', function (Blueprint $table) {
            $table->id();
        });
        DB::table('b2c_cotizacions')->insert(['id' => 1]);

        $this->assertTrue(DB::table('b2c_cotizacions')->exists());

        $source = file_get_contents(app_path(
            'Services/ProductionCleanup/ZigoProductionCleanupService.php'
        ));

        $this->assertStringContainsString(
            "Schema::hasTable('b2c_cotizacions') && DB::table('b2c_cotizacions')->exists()",
            $source
        );
        $this->assertStringContainsString(
            'La tabla heredada b2c_cotizacions contiene datos sin relación determinista.',
            $source
        );
    }
}
