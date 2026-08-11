<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saas_onboarding_applications', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique('saas_onboarding_uuid_uq');
            $table->string('public_token', 96)->unique('saas_onboarding_public_token_uq');
            $table->string('status', 24)->default('DRAFT')->index();

            $table->string('contact_name');
            $table->string('contact_last_name')->nullable();
            $table->string('contact_email')->index();
            $table->string('contact_phone', 40)->nullable();
            $table->string('company_name');
            $table->string('company_legal_name')->nullable();
            $table->string('tax_id', 32)->nullable();

            $table->foreignId('selected_plan_id')->nullable()
                ->constrained('network_plans')->restrictOnDelete();
            $table->string('billing_period', 16)->default('monthly');
            $table->json('selected_modules_json')->nullable();
            $table->unsignedInteger('requested_operations')->nullable();
            $table->json('commercial_snapshot_json')->nullable();
            $table->decimal('subtotal', 12, 2)->nullable();
            $table->decimal('tax_amount', 12, 2)->nullable();
            $table->decimal('total', 12, 2)->nullable();
            $table->char('currency', 3)->nullable();

            $table->string('requested_subdomain', 63)->nullable();
            // Nullable unique key represents only a currently active reservation.
            $table->string('reserved_subdomain_key', 63)->nullable()
                ->unique('saas_onboarding_reserved_subdomain_uq');
            $table->timestamp('subdomain_reserved_until')->nullable()->index();

            $table->string('purchase_key', 100)->unique('saas_onboarding_purchase_key_uq');
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('provisioning_started_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('expired_at')->nullable();

            $table->foreignId('tenant_id')->nullable()
                ->constrained('network_tenants')->nullOnDelete();
            $table->foreignId('owner_user_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->string('failure_code', 80)->nullable();
            $table->json('failure_context_json')->nullable();
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saas_onboarding_applications');
    }
};
