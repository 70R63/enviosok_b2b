<?php

namespace App\Domain\Network\Providers;

use App\Domain\Network\Billing\EntitlementService;
use App\Domain\Network\Billing\SubscriptionService;
use App\Domain\Network\Channels\B2C\Models\TenantOperation;
use App\Domain\Network\Channels\B2C\TenantOperationService;
use App\Domain\Network\Providers\Models\ProviderConnection;
use App\Domain\Network\Providers\Models\ProviderContract;
use App\Domain\Network\Providers\Models\ProviderFulfillment;
use App\Domain\Network\Providers\Models\ProviderQuoteSnapshot;
use App\Domain\Network\Tenancy\Models\Tenant;
use App\Domain\Shipping\Local\LocalShipmentService;
use App\Domain\Shipping\Local\LocalTrackingService;
use App\Domain\Shipping\Local\Models\LocalShippingService;
use App\Models\B2cCotizacion;
use App\Services\Payments\PaymentVerificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

final class ProviderFulfillmentService
{
    public function __construct(private PaymentVerificationService $payments, private ProviderConnectionService $connections,
        private ProviderQuoteOwnership $ownership, private LocalProviderEligibility $eligibility,
        private SubscriptionService $subscriptions, private EntitlementService $entitlements,
        private TenantOperationService $operations, private LocalShipmentService $shipments, private LocalTrackingService $tracking) {}

    /** Internal payment-confirmation/recovery entry point. No browser tenant IDs. */
    public function fulfill(B2cCotizacion $quote): ProviderFulfillment
    {
        return DB::transaction(function () use ($quote): ProviderFulfillment {
            $quote = B2cCotizacion::whereKey($quote->id)->lockForUpdate()->firstOrFail();
            $contract = ProviderContract::where('b2c_cotizacion_id', $quote->id)->lockForUpdate()->first();
            $offer = $contract ? ProviderQuoteSnapshot::whereKey($contract->quote_snapshot_id)->lockForUpdate()->first() : null;
            $this->require($contract && $offer && $quote->provider === 'tenant_provider_local'
                && $quote->provider_quote_reference === $contract->uuid
                && $offer->quote_source === 'tenant_provider_local' && $offer->capability === 'LOCAL_SHIPPING'
                && (int) $offer->b2c_cotizacion_id === (int) $quote->id
                && $contract->selected_at->lt($offer->expires_at)
                && (! $offer->valid_from || $contract->selected_at->gte($offer->valid_from))
                && hash_equals($offer->request_fingerprint, $this->ownership->fingerprint($quote)), 'INVALID_CONTRACT');
            // Freeze provider billing authority before any consistent-read payment queries.
            // MySQL/MariaDB REPEATABLE READ must not retain an older subscription view.
            $tenants = Tenant::whereIn('id', [$offer->consumer_tenant_id, $offer->provider_tenant_id])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $consumer = $tenants->get($offer->consumer_tenant_id);
            $provider = $tenants->get($offer->provider_tenant_id);
            $this->require($consumer && $provider, 'INVALID_CONTRACT');
            $this->entitlements->lockCurrentAuthority($provider);
            $this->require(in_array($quote->estatus, ['PAGADA', 'GUIA_GENERADA'], true)
                && $quote->payment_verification_status === 'VERIFIED' && $quote->payment_verified_at
                && $this->payments->isEligibleForGuide($quote)
                && $quote->payment_verified_currency === $offer->currency
                && (string) $quote->precio === (string) $offer->total_amount, 'PAYMENT_NOT_CONFIRMED');
            $existing = ProviderFulfillment::where('provider_contract_id', $contract->id)->first();
            if ($existing) {
                return $existing;
            }
            // A destructive bridge rollback must never cause a second shipment.
            $this->require(! TenantOperation::where('source_type', 'provider_contract')->where('source_id', $contract->id)->exists(),
                'FULFILLMENT_LINK_REQUIRES_REPAIR');

            $connection = ProviderConnection::whereKey($offer->provider_connection_id)->lockForUpdate()->first();
            $this->require($consumer && $provider && $connection
                && $consumer->status === 'active' && $provider->status === 'active'
                && $connection->status === 'active'
                && (! $connection->valid_from || $connection->valid_from->lte(now()))
                && (! $connection->valid_to || $connection->valid_to->gt(now()))
                && (! $connection->valid_from || ! $connection->valid_to || $connection->valid_from->lte($connection->valid_to))
                && (int) $connection->consumer_tenant_id === (int) $consumer->id
                && (int) $connection->provider_tenant_id === (int) $provider->id
                && $connection->capability === 'LOCAL_SHIPPING'
                && $this->connections->canConsume($consumer, $provider, 'LOCAL_SHIPPING'), 'AUTHORIZATION_REVOKED');
            $grant = DB::table('network_provider_connection_services')->where('connection_id', $connection->id)
                ->where('provider_tenant_id', $provider->id)->where('capability', 'LOCAL_SHIPPING')
                ->where('local_shipping_service_id', $offer->local_shipping_service_id)->lockForUpdate()->first();
            $service = LocalShippingService::where('tenant_id', $provider->id)->whereKey($offer->local_shipping_service_id)->lockForUpdate()->first();
            $this->require($grant && $service && $service->code === $offer->service_snapshot['code']
                && $service->status === 'active' && $service->published
                && (! $service->valid_from || $service->valid_from->lte(now()))
                && (! $service->valid_to || $service->valid_to->gt(now()))
                && $this->connections->authorizedLocalServices($consumer, $provider)->contains('id', $service->id)
                && $this->eligibility->allows($service, $quote, true), 'SERVICE_UNAVAILABLE');
            $sender = $this->address($quote, 'remitente', 'origen');
            $recipient = $this->address($quote, 'destinatario', 'destino');
            $package = $offer->package_snapshot;
            if ($package['type'] === 'caja') {
                $dimensions = preg_split('/x/i', $package['dimensions']);
                $package += array_combine(['length', 'width', 'height'], array_map('floatval', $dimensions));
            }
            $package['content'] = $quote->contenido;
            $package['declared_value'] = $quote->valor_declarado;
            $subscription = $this->subscriptions->activeForTenant($provider);
            $this->require($subscription && $this->entitlements->has($provider, 'LOCAL_SHIPPING'), 'PROVIDER_NOT_OPERATIONAL');
            $operation = TenantOperation::create(['tenant_id' => $provider->id, 'subscription_id' => $subscription->id,
                'channel' => 'b2c', 'status' => 'quoted', 'source_type' => 'provider_contract', 'source_id' => $contract->id,
                'provider' => 'ZIGO_LOCAL', 'service_code' => $offer->service_snapshot['code'],
                'external_reference' => $contract->uuid, 'created_by_user_id' => null, 'customer_profile_id' => null,
                'metadata' => ['consumer_tenant_id' => $consumer->id, 'provider_contract_uuid' => $contract->uuid,
                    'pickup_request' => data_get($quote->guia_request_snapshot, 'pickup_request')]]);
            $operation = $this->operations->confirm($provider, $operation);
            $shipment = $this->shipments->create($provider, $operation, ['sender' => $sender, 'recipient' => $recipient,
                'package' => $package, 'pricing' => ['provider_cost' => $offer->provider_cost, 'currency' => $offer->currency,
                    'cost_basis' => 'net', 'source' => $offer->service_snapshot['pricing_source'], 'tariff' => $offer->tariff_snapshot],
                'reference' => null, 'service_name' => $offer->service_snapshot['name']], null, $consumer);
            $this->tracking->transition($shipment, 'READY_FOR_PICKUP', null, 'Recolección solicitada después de compra confirmada.');
            $bridge = ProviderFulfillment::forceCreate(['provider_contract_id' => $contract->id, 'quote_snapshot_id' => $offer->id,
                'consumer_tenant_id' => $consumer->id, 'provider_tenant_id' => $provider->id,
                'tenant_operation_id' => $operation->id, 'local_shipment_id' => $shipment->id,
                'commercial_confirmed_at' => $quote->payment_verified_at, 'pickup_requested_at' => now()]);
            $quote->forceFill(['guia_id' => $shipment->tracking_number, 'tracking_number' => $shipment->tracking_number,
                'guia_estatus' => 'GENERADA', 'guia_generated_at' => now(), 'estatus' => 'GUIA_GENERADA'])->save();

            return $bridge;
        }, 5);
    }

    private function require(bool $condition, string $code): void
    {
        if (! $condition) {
            throw new \DomainException($code);
        }
    }

    public static function failureCode(\Throwable $exception): string
    {
        $codes = ['INVALID_CONTRACT', 'PAYMENT_NOT_CONFIRMED', 'FULFILLMENT_LINK_REQUIRES_REPAIR',
            'AUTHORIZATION_REVOKED', 'SERVICE_UNAVAILABLE', 'PROVIDER_NOT_OPERATIONAL'];

        return $exception instanceof \DomainException && in_array($exception->getMessage(), $codes, true)
            ? $exception->getMessage() : 'FULFILLMENT_FAILED';
    }

    private function address(B2cCotizacion $q, string $person, string $side): array
    {
        $party = ['name' => $q->{$person.'_nombre'}, 'phone' => $q->{$person.'_telefono'}, 'email' => $q->{$person.'_email'},
            'address' => ['street' => $q->{$person.'_direccion'}, 'exterior' => $q->{$person.'_num_ext'},
                'interior' => $q->{$person.'_num_int'}, 'settlement' => $q->{'colonia_'.$side},
                'postal_code' => $q->{'cp_'.$side}, 'municipality' => $q->{'ciudad_'.$side},
                'state' => $q->{'estado_'.$side}, 'country' => 'México', 'references' => $q->{$person.'_referencias'}]];
        Validator::make($party, ['name' => 'required|string|max:255', 'phone' => 'required|string|max:30',
            'address.street' => 'required|string|max:255', 'address.exterior' => 'required|string|max:50',
            'address.settlement' => 'required|string|max:255', 'address.postal_code' => 'required|regex:/^\d{5}$/',
            'address.municipality' => 'required|string|max:255', 'address.state' => 'required|string|max:255'])->validate();

        return $party;
    }
}
