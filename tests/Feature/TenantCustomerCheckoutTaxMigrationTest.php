<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class TenantCustomerCheckoutTaxMigrationTest extends TestCase
{
    public function test_historical_checkout_keeps_total_and_receives_zero_tax_backfill(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite' || DB::connection()->getDatabaseName() !== ':memory:') $this->markTestSkipped('Requires SQLite :memory:.');
        Schema::create('tenant_customer_checkouts', function (Blueprint $table): void {
            $table->id();
            $table->decimal('evidence_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2);
        });
        DB::table('tenant_customer_checkouts')->insert(['id' => 1, 'evidence_amount' => 0, 'total_amount' => 179]);

        $migration = require database_path('migrations/2026_08_15_230000_add_tax_amounts_to_tenant_customer_checkouts.php');
        $migration->up();
        $historical = DB::table('tenant_customer_checkouts')->where('id', 1)->first();
        $this->assertEquals(179, $historical->subtotal_amount);
        $this->assertEquals(0, $historical->tax_rate);
        $this->assertEquals(0, $historical->tax_amount);
        $this->assertEquals(179, $historical->total_amount);
        $migration->down();
    }
}
