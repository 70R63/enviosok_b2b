<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tenant_customer_checkouts', function (Blueprint $table): void {
            $table->decimal('subtotal_amount', 12, 2)->nullable()->after('evidence_amount');
            $table->decimal('tax_rate', 5, 4)->nullable()->after('subtotal_amount');
            $table->decimal('tax_amount', 12, 2)->nullable()->after('tax_rate');
        });

        DB::table('tenant_customer_checkouts')->whereNull('subtotal_amount')->update([
            'subtotal_amount' => DB::raw('total_amount'),
            'tax_rate' => 0,
            'tax_amount' => 0,
        ]);
    }

    public function down(): void
    {
        Schema::table('tenant_customer_checkouts', function (Blueprint $table): void {
            $table->dropColumn(['subtotal_amount', 'tax_rate', 'tax_amount']);
        });
    }
};
