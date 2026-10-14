<?php

namespace Tests\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final class ProviderFulfillmentFixture
{
    public static function schema(): void
    {
        ProviderContractFixture::schema();
        Schema::table('network_tenant_operations', function (Blueprint $t): void {
            $t->uuid('uuid')->unique();
            $t->unsignedBigInteger('tenant_id');
            $t->unsignedBigInteger('subscription_id')->nullable();
            foreach (['channel', 'status', 'source_type', 'provider', 'service_code', 'external_reference'] as $field) {
                $t->string($field)->nullable();
            }
            foreach (['source_id', 'created_by_user_id', 'customer_profile_id'] as $field) {
                $t->unsignedBigInteger($field)->nullable();
            }
            $t->json('metadata')->nullable();
            $t->timestamps();
        });
        Schema::create('network_plans', fn (Blueprint $t) => $t->id());
        Schema::create('network_subscriptions', function (Blueprint $t): void {
            $t->id();
            $t->uuid('uuid');
            $t->unsignedBigInteger('tenant_id');
            $t->unsignedBigInteger('plan_id');
            $t->string('status');
            $t->unsignedInteger('operations_limit')->nullable();
            foreach (['started_at', 'current_period_start', 'current_period_end'] as $field) {
                $t->dateTime($field);
            }
            $t->timestamps();
        });
        Schema::create('network_entitlements', function (Blueprint $t): void {
            $t->id();
            $t->unsignedBigInteger('tenant_id');
            $t->unsignedBigInteger('subscription_id');
            $t->string('code');
            $t->boolean('is_enabled');
        });
        Schema::create('network_usage_events', function (Blueprint $t): void {
            $t->id();
            $t->uuid('uuid');
            $t->unsignedBigInteger('tenant_id');
            $t->unsignedBigInteger('subscription_id');
            $t->string('metric');
            $t->integer('quantity');
            $t->string('idempotency_key');
            $t->json('metadata');
            $t->dateTime('occurred_at');
            $t->dateTime('created_at');
            $t->unique(['tenant_id', 'metric', 'idempotency_key']);
        });
        Schema::create('network_tenant_brandings', function (Blueprint $t): void {
            $t->id();
            $t->unsignedBigInteger('tenant_id');
            $t->string('brand_name');
            $t->string('logo_path')->nullable();
            $t->string('primary_color')->nullable();
            $t->timestamps();
        });
        Schema::create('b2c_movimientos_saldo', function (Blueprint $t): void {
            $t->id();
            $t->unsignedBigInteger('user_id');
            $t->string('tipo');
            $t->string('referencia');
            $t->string('estatus');
            $t->decimal('monto', 12, 2);
            $t->timestamps();
        });
        Schema::create('b2c_checkout_debt_allocations', function (Blueprint $t): void {
            $t->id();
            $t->unsignedBigInteger('checkout_cotizacion_id');
            $t->string('estatus');
            $t->decimal('monto', 12, 2);
        });
        (require database_path('migrations/2026_10_13_120000_create_network_provider_fulfillments.php'))->up();
        Schema::create('b2c_guide_recovery_tokens', function (Blueprint $t): void {
            $t->id();
            $t->unsignedBigInteger('cotizacion_id');
            $t->string('email_hash', 64);
            $t->string('token_hash', 64)->unique();
            $t->unsignedSmallInteger('attempts')->default(0);
            $t->unsignedSmallInteger('max_attempts')->default(10);
            $t->timestamp('expires_at');
            $t->dateTime('last_used_at')->nullable();
            $t->dateTime('revoked_at')->nullable();
            $t->timestamps();
        });
        Schema::create('b2c_guide_recovery_audits', function (Blueprint $t): void {
            $t->id();
            $t->unsignedBigInteger('cotizacion_id');
            $t->unsignedBigInteger('actor_user_id')->nullable();
            $t->string('action');
            $t->string('result');
            $t->string('correlation_id');
            $t->string('ip_hash')->nullable();
            $t->json('metadata');
            $t->timestamps();
        });
    }

    public static function operational(array $fixture): void
    {
        $provider = $fixture['provider'];
        $plan = DB::table('network_plans')->insertGetId([]);
        $subscription = DB::table('network_subscriptions')->insertGetId(['uuid' => (string) Str::uuid(),
            'tenant_id' => $provider->id, 'plan_id' => $plan, 'status' => 'active',
            'started_at' => now()->subDay(), 'current_period_start' => now()->subDay(), 'current_period_end' => now()->addMonth()]);
        DB::table('network_entitlements')->insert(['tenant_id' => $provider->id, 'subscription_id' => $subscription,
            'code' => 'LOCAL_SHIPPING', 'is_enabled' => true]);
        $provider->branding()->create(['brand_name' => 'Provider Delivery']);
        $fixture['consumer']->branding()->create(['brand_name' => 'Consumer Store']);
        $fixture['quote']->forceFill(self::addresses() + ['remitente_referencias' => 'Puerta azul', 'destinatario_referencias' => 'Timbre 2'])->save();
    }

    public static function addresses(): array
    {
        return ['remitente_nombre' => 'Pickup Contact', 'remitente_telefono' => '8112345678',
            'remitente_direccion' => 'Pickup Street', 'remitente_num_ext' => '10', 'remitente_num_int' => 'A',
            'ciudad_origen' => 'Monterrey', 'estado_origen' => 'Nuevo León',
            'destinatario_nombre' => 'Delivery Contact', 'destinatario_telefono' => '8187654321',
            'destinatario_direccion' => 'Delivery Street', 'destinatario_num_ext' => '20',
            'ciudad_destino' => 'Monterrey', 'estado_destino' => 'Nuevo León', 'contenido' => 'Books'];
    }
}
