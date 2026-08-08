<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('local_shipping_zones', function (Blueprint $t): void {
            $t->id(); $t->string('code', 40)->unique('local_zones_code_uq'); $t->string('name'); $t->string('status', 20)->default('active'); $t->timestamps();
        });
        Schema::create('local_shipping_zone_postal_codes', function (Blueprint $t): void {
            $t->id(); $t->foreignId('zone_id'); $t->char('postal_code', 5); $t->timestamps();
            $t->foreign('zone_id', 'local_zone_cp_zone_fk')->references('id')->on('local_shipping_zones')->cascadeOnDelete();
            $t->unique(['zone_id','postal_code'], 'local_zone_cp_uq'); $t->index('postal_code', 'local_zone_cp_lookup_idx');
        });
        Schema::create('local_shipping_services', function (Blueprint $t): void {
            $t->id(); $t->string('code', 50)->unique('local_services_code_uq'); $t->string('name');
            $t->foreignId('origin_zone_id'); $t->foreignId('destination_zone_id');
            $t->foreign('origin_zone_id', 'local_svc_origin_fk')->references('id')->on('local_shipping_zones');
            $t->foreign('destination_zone_id', 'local_svc_dest_fk')->references('id')->on('local_shipping_zones');
            $t->string('service_level', 30); $t->decimal('base_cost', 12, 2); $t->decimal('base_price', 12, 2); $t->char('currency', 3)->default('MXN');
            foreach (['max_weight','max_length','max_width','max_height'] as $column) $t->decimal($column, 10, 2)->nullable();
            $t->unsignedSmallInteger('estimated_min_hours')->nullable(); $t->unsignedSmallInteger('estimated_max_hours')->nullable(); $t->string('status', 20)->default('active'); $t->timestamps();
            $t->index(['origin_zone_id','destination_zone_id','status'], 'local_svc_route_idx');
        });
        Schema::create('local_shipments', function (Blueprint $t): void {
            $t->id(); $t->uuid('uuid')->unique('local_ship_uuid_uq');
            $t->foreignId('tenant_id'); $t->foreignId('tenant_operation_id')->unique('local_ship_operation_uq');
            $t->foreign('tenant_id', 'local_ship_tenant_fk')->references('id')->on('network_tenants');
            $t->foreign('tenant_operation_id', 'local_ship_operation_fk')->references('id')->on('network_tenant_operations');
            $t->string('tracking_number', 32)->unique('local_ship_tracking_uq'); $t->string('service_code', 50); $t->string('status', 30);
            $t->json('sender_snapshot'); $t->json('recipient_snapshot'); $t->json('package_snapshot'); $t->json('pricing_snapshot'); $t->json('guide_snapshot');
            $t->foreignId('created_by_user_id')->nullable(); $t->timestamps();
            $t->foreign('created_by_user_id', 'local_ship_creator_fk')->references('id')->on('users');
            $t->index(['tenant_id','created_at'], 'local_ship_tenant_date_idx');
        });
        Schema::create('local_tracking_events', function (Blueprint $t): void {
            $t->id(); $t->foreignId('local_shipment_id');
            $t->foreign('local_shipment_id', 'local_track_ship_fk')->references('id')->on('local_shipments')->cascadeOnDelete();
            $t->string('status', 30); $t->string('event_code', 50); $t->text('description')->nullable(); $t->timestamp('occurred_at');
            $t->foreignId('created_by_user_id')->nullable(); $t->json('metadata')->nullable(); $t->timestamp('created_at')->nullable();
            $t->foreign('created_by_user_id', 'local_track_creator_fk')->references('id')->on('users');
            $t->index(['local_shipment_id','occurred_at'], 'local_track_timeline_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('local_tracking_events'); Schema::dropIfExists('local_shipments'); Schema::dropIfExists('local_shipping_services'); Schema::dropIfExists('local_shipping_zone_postal_codes'); Schema::dropIfExists('local_shipping_zones');
    }
};
