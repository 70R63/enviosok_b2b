<?php

namespace App\Domain\Network\Providers;

use App\Domain\Network\Billing\EntitlementService;
use App\Domain\Network\Billing\SubscriptionService;
use App\Domain\Network\Providers\Crm\ProviderCrmAdapterResolver;
use App\Domain\Network\Providers\Models\ProviderConnection;
use App\Domain\Network\Providers\Models\ProviderIntegration;
use App\Domain\Network\Tenancy\Models\Tenant;
use App\Domain\Shipping\Local\Models\LocalShippingPackageRule;
use App\Domain\Shipping\Local\Models\LocalShippingService;
use App\Domain\Shipping\Local\Models\LocalShippingZone;
use App\Domain\Shipping\Local\Models\LocalShippingZonePostalCode;
use App\Domain\Shipping\Local\Pricing\LocalPricingEngine;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Read-only configuration diagnosis; never quotes a customer or creates logistics. */
final class ProviderNetworkReadinessService
{
    public function __construct(private LocalProviderCommercialPricing $commercial, private LocalPricingEngine $pricing) {}

    public function evaluate(?Tenant $consumer, ?Tenant $provider, ?ProviderConnection $connection = null,
        bool $forActivation = false, string $segment = 'anonymous'): array
    {
        $checks = [];
        $add = function (string $code, string $status, string $message) use (&$checks): void {
            $checks[] = compact('code', 'status', 'message');
        };
        $test = fn (string $code, bool $ok, string $message) => $add($code, $ok ? 'PASS' : 'FAIL', $message);
        $test('consumer.exists', (bool) $consumer?->exists, 'Consumer existente');
        $test('consumer.active', $consumer?->status === 'active', 'Consumer activo');
        $test('provider.exists', (bool) $provider?->exists, 'Provider existente');
        $test('provider.active', $provider?->status === 'active', 'Provider activo');
        $test('tenants.distinct', $consumer && $provider && $consumer->id !== $provider->id, 'Consumer y provider distintos');
        $tables = ['network_provider_connections', 'network_provider_connection_services', 'local_shipping_services',
            'local_shipping_zones', 'local_shipping_zone_postal_codes', 'local_shipping_pricing_rules',
            'local_shipping_package_rules', 'zigo_pricing_rules', 'network_tenant_memberships', 'users',
            'network_subscriptions', 'network_entitlements', 'network_plans'];
        $catalogAvailable = collect($tables)->every(fn ($table) => Schema::hasTable($table));
        $test('schema.configuration', $catalogAvailable, 'Esquema de configuración disponible; aplicar migraciones faltantes');
        if (! $consumer || ! $provider || ! $catalogAvailable) {
            return $this->report($checks);
        }
        $connection ??= ProviderConnection::where('consumer_tenant_id', $consumer->id)->where('provider_tenant_id', $provider->id)
            ->where('capability', ProviderConnection::LOCAL_SHIPPING)->first();
        $owned = $connection && $connection->exists && $connection->consumer_tenant_id === $consumer->id && $connection->provider_tenant_id === $provider->id;
        $test('connection.exists', (bool) $owned, 'Relación consumer/provider existente');
        $test('connection.active', $owned && ($connection->status === 'active' || ($forActivation && $connection->status === 'inactive')),
            $forActivation ? 'Estado válido para evaluar activación (todavía no activa)' : 'Conexión activa');
        $test('connection.validity', $owned && $this->current($connection), 'Conexión vigente y fechas consistentes');
        $test('connection.capability', $owned && $connection->capability === ProviderConnection::LOCAL_SHIPPING, 'Capability LOCAL_SHIPPING');

        $grants = $owned ? DB::table('network_provider_connection_services')->where('connection_id', $connection->id)->get() : collect();
        $test('services.authorized', $grants->isNotEmpty(), $grants->count().' servicios autorizados');
        $services = LocalShippingService::whereIn('id', $grants->pluck('local_shipping_service_id'))->get();
        $test('services.ownership', $grants->isNotEmpty() && $services->count() === $grants->count()
            && $grants->every(fn ($g) => (int) $g->provider_tenant_id === $provider->id && $g->capability === ProviderConnection::LOCAL_SHIPPING)
            && $services->every(fn ($s) => (int) $s->tenant_id === $provider->id), 'Todos los servicios pertenecen al provider');
        $test('services.active', $services->isNotEmpty() && $services->every(fn ($s) => $s->status === 'active'), 'Servicios activos');
        $test('services.published', $services->isNotEmpty() && $services->every(fn ($s) => $s->published), 'Servicios publicados');
        $test('services.validity', $services->isNotEmpty() && $services->every(fn ($s) => $this->current($s)), 'Servicios vigentes');
        $test('services.supported', $services->isNotEmpty() && $services->every(fn ($s) => $s->currency === 'MXN'
            && in_array($s->pricing_strategy, ['FLAT', 'PACKAGE_FLAT'], true)), 'Moneda y estrategia compatibles con cotizador provider');

        $zones = LocalShippingZone::where('tenant_id', $provider->id)->where('status', 'active')->pluck('id');
        $postal = $this->currentQuery(LocalShippingZonePostalCode::where('tenant_id', $provider->id)->whereIn('zone_id', $zones)->where('active', true))
            ->get()->filter(fn ($cp) => preg_match('/^\d{5}$/', $cp->postal_code) === 1);
        $test('coverage.zones', $zones->isNotEmpty(), $zones->count().' zonas activas del provider');
        $test('coverage.postal_codes', $postal->isNotEmpty(), $postal->pluck('postal_code')->unique()->count().' CP activos y vigentes del provider');
        $covered = $services->filter(fn ($s) => (int) $s->tenant_id === $provider->id && $zones->contains($s->origin_zone_id)
            && $zones->contains($s->destination_zone_id) && $postal->contains('zone_id', $s->origin_zone_id) && $postal->contains('zone_id', $s->destination_zone_id));
        $test('coverage.services', $services->isNotEmpty() && $covered->count() === $services->count(), $covered->count().' servicios con cobertura origen y destino');

        $rateReady = $services->isNotEmpty();
        $commercialReady = $services->isNotEmpty();
        $packagesReady = $services->isNotEmpty();
        foreach ($services as $service) {
            if ((int) $service->tenant_id !== $provider->id) {
                $rateReady = $commercialReady = $packagesReady = false;

                continue;
            }
            $rules = LocalShippingPackageRule::where('tenant_id', $provider->id)->where('active', true)
                ->whereIn('package_type', ['caja', 'sobre'])->where(fn ($q) => $q->where('service_id', $service->id)->orWhereNull('service_id'))
                ->orderByRaw('CASE WHEN service_id IS NULL THEN 1 ELSE 0 END')->orderBy('id')->get()->unique('package_type');
            $packageReady = $rules->isNotEmpty() && $rules->every(fn ($r) => (float) $r->max_weight_kg > 0
                && ($r->package_type === 'sobre' || collect([$r->max_dimension_1_cm, $r->max_dimension_2_cm, $r->max_dimension_3_cm])->every(fn ($v) => (float) $v > 0)));
            $packagesReady = $packagesReady && $packageReady;
            $test('package.'.$service->id, $packageReady, 'Reglas de paquete configuradas: '.$service->name);
            if ($rules->isEmpty()) {
                $rateReady = $commercialReady = false;
                $test('rate.'.$service->id, false, 'Tarifa operacional no verificable sin tipo de paquete: '.$service->name);
                $test('commercial.'.$service->id, false, 'Pricing comercial no verificable sin tipo de paquete: '.$service->name);
            }
            foreach ($rules as $rule) {
                $type = $rule->package_type;
                $service->setRelation('pricingRules', $this->currentQuery($service->pricingRules()->getQuery()->where('tenant_id', $provider->id)
                    ->where('active', true)->where(fn ($q) => $q->whereNull('package_type')->orWhere('package_type', $type)))->orderBy('id')->get());
                $price = null;
                try {
                    $price = $this->pricing->price($service, $type, null);
                } catch (\DomainException) {
                }
                $rateReady = $rateReady && $price && (float) $price->totalAmount > 0;
                $test('rate.'.$service->id.'.'.$type, $price && (float) $price->totalAmount > 0,
                    'Tarifa operacional aplicable: '.$service->name.' / '.$type);
                $commercial = $price ? $this->commercial->calculate($consumer, $provider, $price->totalAmount, $segment, $type) : null;
                $commercialReady = $commercialReady && $commercial !== null;
                $test('commercial.'.$service->id.'.'.$type, $commercial !== null, 'Pricing comercial consumer/provider ('.$segment.'): '.$service->name.' / '.$type);
            }
        }
        $test('package.configured', $packagesReady, 'Reglas de paquete aplicables para todos los servicios autorizados');
        $test('rate.provider', $rateReady, 'Provider operational pricing: tarifas aplicables para los paquetes configurados');
        $test('pricing.commercial', $commercialReady, 'Consumer commercial pricing: reglas explícitas para los paquetes configurados');
        $operators = DB::table('network_tenant_memberships')->where('tenant_id', $provider->id)->where('status', 'active')
            ->whereIn('role', ['owner', 'admin', 'operator'])->whereExists(fn ($q) => $q->selectRaw('1')->from('users')->whereColumn('users.id', 'network_tenant_memberships.user_id'))->count();
        $test('operation.members', $operators > 0, $operators.' miembros operativos activos; necesarios para inbox interno');
        $subscription = app(SubscriptionService::class)->activeForTenant($provider);
        $test('operation.authority', $subscription && app(EntitlementService::class)->has($provider, 'LOCAL_SHIPPING'), 'Suscripción operacional y entitlement LOCAL_SHIPPING');
        $driverLicensed = $subscription && app(EntitlementService::class)->has($provider, 'DRIVER');
        $add('dispatch.license', $driverLicensed ? 'PASS' : 'WARN', 'Entitlement DRIVER requerido antes de asignar conductores');
        $drivers = Schema::hasTable('tenant_driver_profiles') ? DB::table('tenant_driver_profiles')->where('tenant_id', $provider->id)->where('status', 'ACTIVE')->count() : 0;
        $add('dispatch.drivers', $drivers > 0 ? 'PASS' : 'WARN', $drivers.' drivers activos; completar disponibilidad antes de la prueba operativa');
        $infrastructure = [
            'network_provider_quote_snapshots' => ['consumer_tenant_id', 'provider_tenant_id', 'provider_connection_id'],
            'network_provider_contracts' => ['quote_snapshot_id', 'b2c_cotizacion_id'],
            'network_provider_fulfillments' => ['provider_contract_id', 'provider_tenant_id', 'tenant_operation_id', 'local_shipment_id'],
            'network_tenant_operations' => ['tenant_id', 'source_type', 'source_id'],
            'local_shipments' => ['tenant_id', 'tenant_operation_id', 'sender_snapshot', 'recipient_snapshot', 'guide_snapshot'],
            'local_tracking_events' => ['local_shipment_id', 'status', 'metadata'],
            'network_provider_fulfillment_decisions' => ['provider_fulfillment_id', 'status'],
            'local_driver_assignments' => ['tenant_id', 'local_shipment_id'],
            'local_delivery_proofs' => ['tenant_id', 'local_shipment_id'],
            'local_delivery_failed_attempts' => ['tenant_id', 'local_shipment_id'],
            'b2c_guide_recovery_tokens' => ['cotizacion_id', 'token_hash'],
            'b2c_cotizaciones' => ['provider_quote_reference', 'payment_verification_status', 'provider_fulfillment_attempted_at'],
        ];
        $test('fulfillment.schema', collect($infrastructure)->every(fn ($columns, $table) => Schema::hasTable($table)
            && ! array_diff($columns, Schema::getColumnListing($table))), 'Infraestructura fulfillment, tracking, POD y recuperación disponible');
        $commands = collect(app(Kernel::class)->resolveConsoleSchedule()->events())->pluck('command')->implode(' ');
        $test('scheduler.application', str_contains($commands, 'network:provider-fulfillment:retry') && str_contains($commands, 'network:provider-crm:deliver'), 'Recovery y outbox registrados en scheduler de aplicación');
        $add('scheduler.host', 'WARN', 'Verificar cron schedule:run, permisos y ejecución en el host; no comprobables desde este diagnóstico');
        $test('settlement.infrastructure', collect(['network_provider_payables', 'network_provider_payable_events', 'network_provider_settlement_entries'])->every(fn ($t) => Schema::hasTable($t))
            && str_contains($commands, 'network:provider-payables:reconcile'), 'Ledger y reconciliación disponibles; liquidación manual válida, sin datos bancarios ni payout automático');
        $this->crm($connection, $provider, $add);

        return $this->report($checks);
    }

    private function crm(?ProviderConnection $connection, Tenant $provider, callable $add): void
    {
        if (! Schema::hasTable('network_provider_integrations') || ! $connection) {
            $add('crm.mode', 'PASS', 'INTERNAL / TENANT_UI: CRM no configurado; no se requiere');

            return;
        }
        $integrations = ProviderIntegration::where('provider_connection_id', $connection->id)->get();
        $external = $integrations->filter(fn ($i) => $i->status === 'active' && $i->adapter !== 'disabled');
        if ($external->isEmpty()) {
            $add('crm.mode', 'PASS', 'INTERNAL / TENANT_UI: CRM deshabilitado; no bloquea operación');

            return;
        }
        foreach ($external as $integration) {
            if (! Schema::hasTable('tenant_api_clients') || ! Schema::hasTable('tenant_api_keys')) {
                $add('crm.external.'.$integration->id, 'FAIL', 'EXTERNAL: esquema API Hub incompleto');

                continue;
            }
            $client = DB::table('tenant_api_clients')->where('id', $integration->api_client_id)->where('tenant_id', $provider->id)->where('status', 'ACTIVE')->exists();
            try {
                $adapterEnabled = app(ProviderCrmAdapterResolver::class)->resolve($integration)->enabled();
            } catch (\Throwable) {
                $adapterEnabled = false;
            }
            $keys = DB::table('tenant_api_keys')->where('api_client_id', $integration->api_client_id)->where('tenant_id', $provider->id)->whereNull('revoked_at')
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))->get(['scopes']);
            $readKey = $keys->contains(fn ($key) => in_array('provider:fulfillment:read', json_decode($key->scopes ?? '[]', true) ?: [], true));
            $ok = $client && $this->current($integration) && $adapterEnabled && $readKey
                && app(EntitlementService::class)->has($provider, 'API');
            $add('crm.external.'.$integration->id, $ok ? 'PASS' : 'FAIL', 'EXTERNAL: adapter habilitado, cliente/vigencia, clave read y entitlement API');
            $add('crm.contract.'.$integration->id, 'WARN', 'Validar contrato remoto y scopes de escritura requeridos antes de habilitar interoperabilidad; no se realiza HTTP');
        }
    }

    private function current($model): bool
    {
        return (! $model->valid_from || $model->valid_from->lte(now())) && (! $model->valid_to || $model->valid_to->gt(now()))
            && (! $model->valid_from || ! $model->valid_to || $model->valid_from->lte($model->valid_to));
    }

    private function currentQuery(Builder $query): Builder
    {
        return $query->where(fn ($q) => $q->whereNull('valid_from')->orWhere('valid_from', '<=', now()))
            ->where(fn ($q) => $q->whereNull('valid_to')->orWhere('valid_to', '>', now()))
            ->where(fn ($q) => $q->whereNull('valid_from')->orWhereNull('valid_to')->orWhereColumn('valid_from', '<=', 'valid_to'));
    }

    private function report(array $checks): array
    {
        return ['ready' => ! collect($checks)->contains('status', 'FAIL'), 'checks' => $checks];
    }
}
