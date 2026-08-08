<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('network_tenant_operations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('network_tenants');
            $table->foreignId('subscription_id')->nullable()->constrained('network_subscriptions');
            $table->string('channel', 30);
            $table->string('status', 30);
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('provider')->nullable();
            $table->string('service_code')->nullable();
            $table->string('external_reference')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'created_at']);
            $table->index(['source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('network_tenant_operations');
    }
};
