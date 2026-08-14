<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_customer_addresses', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('network_tenants')->cascadeOnDelete();
            $table->foreignId('customer_profile_id')->constrained('tenant_customer_profiles')->cascadeOnDelete();
            $table->string('address_type', 20);
            $table->string('alias', 100);
            $table->string('contact_name', 120);
            $table->string('company', 160)->nullable();
            $table->string('phone', 30);
            $table->string('email', 160)->nullable();
            $table->string('street', 180);
            $table->string('exterior', 40);
            $table->string('interior', 40)->nullable();
            $table->char('postal_code', 5);
            $table->string('settlement', 160);
            $table->string('municipality', 160);
            $table->string('state', 160);
            $table->string('references', 300)->nullable();
            $table->boolean('is_default_origin')->default(false);
            $table->boolean('is_default_destination')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['tenant_id', 'customer_profile_id', 'is_active'], 'tenant_customer_addresses_owner_active_idx');
            $table->index(['tenant_id', 'customer_profile_id', 'address_type'], 'tenant_customer_addresses_owner_type_idx');
            $table->index(['tenant_id', 'customer_profile_id', 'postal_code', 'settlement'], 'tenant_customer_addresses_route_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_customer_addresses');
    }
};
