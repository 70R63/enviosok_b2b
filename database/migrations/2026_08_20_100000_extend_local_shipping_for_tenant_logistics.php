<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('local_shipping_zones', function (Blueprint $table): void {
            $table->dropUnique('local_zones_code_uq');
            $table->foreignId('tenant_id')->nullable()->after('id')->index();
            $table->string('coverage_mode', 30)->default('POSTAL_POOL')->after('name');
            $table->unique(['tenant_id','code'],'local_zones_tenant_code_uq');
        });
        Schema::table('local_shipping_zone_postal_codes', function (Blueprint $table): void {
            $table->foreignId('tenant_id')->nullable()->after('id')->index();
            $table->string('state')->nullable();
            $table->string('municipality')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_to')->nullable();
            $table->json('metadata')->nullable();
        });
        Schema::table('local_shipping_services', function (Blueprint $table): void {
            $table->dropUnique('local_services_code_uq');
            $table->foreignId('tenant_id')->nullable()->after('id')->index();
            $table->text('description')->nullable()->after('name');
            $table->boolean('published')->default(false)->after('status');
            $table->string('pricing_strategy', 40)->default('FLAT');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('sla_text')->nullable();
            $table->string('service_type', 40)->nullable();
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_to')->nullable();
            $table->unique(['tenant_id','code'],'local_services_tenant_code_uq');
        });
        Schema::create('local_shipping_pricing_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->index();
            $table->foreignId('service_id');
            $table->string('package_type', 20)->nullable();
            $table->decimal('from_km', 10, 3)->nullable();
            $table->decimal('to_km', 10, 3)->nullable();
            $table->decimal('amount', 12, 2);
            $table->decimal('included_distance_km', 10, 3)->nullable();
            $table->decimal('overage_price_per_km', 12, 2)->nullable();
            $table->string('overage_rounding', 20)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_to')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->foreign('service_id')->references('id')->on('local_shipping_services')->cascadeOnDelete();
            $table->index(['tenant_id', 'service_id', 'active'], 'local_price_tenant_service_idx');
        });
        Schema::create('local_shipping_package_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->index();
            $table->foreignId('service_id')->nullable();
            $table->string('package_type', 20);
            $table->decimal('max_weight_kg', 10, 2);
            $table->decimal('max_dimension_1_cm', 10, 2)->nullable();
            $table->decimal('max_dimension_2_cm', 10, 2)->nullable();
            $table->decimal('max_dimension_3_cm', 10, 2)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->foreign('service_id')->references('id')->on('local_shipping_services')->cascadeOnDelete();
            $table->unique(['tenant_id', 'service_id', 'package_type'], 'local_package_rule_uq');
        });
        Schema::create('local_shipping_quote_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->index();
            $table->foreignId('service_id');
            $table->json('origin');
            $table->json('destination');
            $table->string('package_type', 20);
            $table->decimal('weight_kg', 10, 2);
            $table->json('dimensions')->nullable();
            $table->unsignedBigInteger('distance_meters')->nullable();
            $table->string('pricing_strategy', 40);
            $table->json('matched_tariff');
            $table->decimal('amount', 12, 2);
            $table->char('currency', 3)->default('MXN');
            $table->timestamp('expires_at');
            $table->timestamps();
            $table->foreign('service_id')->references('id')->on('local_shipping_services')->cascadeOnDelete();
            $table->index(['tenant_id', 'expires_at'], 'local_quote_tenant_expiry_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('local_shipping_quote_snapshots');
        Schema::dropIfExists('local_shipping_package_rules');
        Schema::dropIfExists('local_shipping_pricing_rules');
        Schema::table('local_shipping_services', fn (Blueprint $table) => $table->dropColumn(['tenant_id','description','published','pricing_strategy','sort_order','sla_text','service_type','valid_from','valid_to']));
        Schema::table('local_shipping_zone_postal_codes', fn (Blueprint $table) => $table->dropColumn(['tenant_id','state','municipality','active','valid_from','valid_to','metadata']));
        Schema::table('local_shipping_zones', fn (Blueprint $table) => $table->dropColumn(['tenant_id','coverage_mode']));
    }
};
