<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tenant_saas_fiscal_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained('network_tenants')->restrictOnDelete();
            $table->string('legal_name');
            $table->string('tax_id', 13)->index();
            $table->string('fiscal_postal_code', 5);
            $table->string('fiscal_regime', 3);
            $table->string('cfdi_use', 4);
            $table->string('billing_email');
            $table->timestamps();
        });

        Schema::create('tenant_saas_invoice_requests', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('network_tenants')->restrictOnDelete();
            $table->foreignId('tenant_saas_order_id')->unique()->constrained('tenant_saas_orders')->restrictOnDelete();
            $table->foreignId('platform_payment_attempt_id')->constrained('platform_payment_attempts')->restrictOnDelete();
            $table->foreignId('requested_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('issued_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('REQUESTED')->index();
            $table->decimal('subtotal', 12, 2);
            $table->decimal('tax_amount', 12, 2);
            $table->decimal('total', 12, 2);
            $table->char('currency', 3);
            $table->json('fiscal_snapshot');
            $table->string('pdf_path', 500)->nullable();
            $table->string('xml_path', 500)->nullable();
            $table->timestamp('requested_at');
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->string('rejection_reason', 500)->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_saas_invoice_requests');
        Schema::dropIfExists('tenant_saas_fiscal_profiles');
    }
};
