<?php

namespace App\Domain\Network\Channels\B2C;

use App\Domain\Network\Billing\SubscriptionService;
use App\Domain\Network\Channels\B2C\Exceptions\TenantQuoteUnavailableException;
use App\Domain\Network\Channels\B2C\Models\TenantOperation;
use App\Domain\Network\Tenancy\Models\Tenant;
use App\Models\B2cCotizacion;
use App\Services\ZigoCommercialQuoteService;
use App\Services\ZigoProviderRateService;
use Illuminate\Support\Facades\DB;

final class TenantB2cQuoteService
{
    public function __construct(private ZigoProviderRateService $providers, private ZigoCommercialQuoteService $commercial, private SubscriptionService $subscriptions) {}

    public function quote(Tenant $tenant, array $data): array
    {
        return DB::transaction(function () use ($tenant, $data): array {
            [$weight, $dimensions] = $this->package($data);
            $quote = B2cCotizacion::create([
                'user_id' => null, 'cp_origen' => $data['cp_origen'], 'cp_destino' => $data['cp_destino'],
                'tipo_envio' => $data['tipo_envio'], 'peso' => $weight, 'peso_real' => (float) $data['peso'],
                'peso_facturable' => $weight, 'medidas' => $dimensions, 'estatus' => 'COTIZADA',
                'referencia' => 'TENANT_B2C',
            ]);

            $publicOptions = [];
            foreach ($this->providers->getOptionsForCotizacion($quote) as $option) {
                if (($option['is_fallback_rate'] ?? false) === true) {
                    continue;
                }
                $priced = $this->commercial->calculate($quote, $option, [
                    'customer_segment' => 'anonymous', 'package_type' => $data['tipo_envio'],
                    'plan' => $this->subscriptions->currentForTenant($tenant)?->plan?->code,
                ]);
                $publicOptions[] = [
                    'carrier' => $priced['carrier'], 'service' => $priced['service'],
                    'service_code' => (string) ($option['service_code'] ?? $option['servicio']),
                    'provider' => (string) ($option['provider_source'] ?? strtolower($priced['carrier'])),
                    'price' => (float) $priced['final_price'],
                    'delivery' => $option['entrega'] ?? null,
                ];
            }

            if ($publicOptions === []) {
                throw TenantQuoteUnavailableException::noCommercialRates();
            }

            $operation = TenantOperation::create([
                'tenant_id' => $tenant->id,
                'subscription_id' => $this->subscriptions->currentForTenant($tenant)?->id,
                'channel' => 'b2c', 'status' => 'quoted',
                'source_type' => B2cCotizacion::class, 'source_id' => $quote->id,
                'provider' => $publicOptions[0]['provider'] ?? null,
                'service_code' => $publicOptions[0]['service_code'] ?? null,
                'metadata' => ['origin_postal_code' => $data['cp_origen'], 'destination_postal_code' => $data['cp_destino'], 'package_type' => $data['tipo_envio']],
            ]);

            return ['operation' => $operation, 'options' => $publicOptions];
        });
    }

    private function package(array $data): array
    {
        if ($data['tipo_envio'] === 'sobre') return [1.0, null];
        $dimensions = sprintf('%sx%sx%s', $data['length'], $data['width'], $data['height']);
        $volumetric = ((float) $data['length'] * (float) $data['width'] * (float) $data['height']) / 5000;
        return [ceil(max((float) $data['peso'], $volumetric)), $dimensions];
    }
}
