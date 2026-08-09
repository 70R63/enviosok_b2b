<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tenant_customer_checkouts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique('customer_checkout_uuid_uq');
            $table->foreignId('tenant_id');
            $table->foreignId('customer_profile_id');
            $table->foreignId('tenant_operation_id');
            $table->string('status', 24)->default('DRAFT');
            $table->string('payment_status', 24)->default('PENDING');
            $table->char('currency', 3)->default('MXN');
            $table->decimal('shipping_amount', 12, 2);
            $table->decimal('evidence_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2);
            $table->json('quote_snapshot');
            $table->json('shipping_data_snapshot');
            $table->json('proof_option_snapshot');
            $table->string('payment_provider', 40)->nullable();
            $table->string('payment_reference', 160)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->foreign('tenant_id', 'customer_checkout_tenant_fk')->references('id')->on('network_tenants');
            $table->foreign('customer_profile_id', 'customer_checkout_profile_fk')->references('id')->on('tenant_customer_profiles');
            $table->foreign('tenant_operation_id', 'customer_checkout_operation_fk')->references('id')->on('network_tenant_operations');
            $table->unique('tenant_operation_id', 'customer_checkout_operation_uq');
            $table->index(['tenant_id', 'status'], 'customer_checkout_tenant_status_idx');
            $table->index(['customer_profile_id', 'created_at'], 'customer_checkout_profile_date_idx');
            $table->unique(['payment_provider', 'payment_reference'], 'customer_checkout_payment_ref_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_customer_checkouts');
    }
};
