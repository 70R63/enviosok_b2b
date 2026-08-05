<?php

namespace App\Services\Pricing;

use App\Models\ZigoCommercialConceptRule;
use App\Services\ZigoPricingService;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

final class ZigoCommercialPricingEngine
{
    private const CONCEPTS = ['base', 'area_extendida', 'kg_extra', 'seguro', 'otros'];

    public function calculate(array $provider, array $context, string $service, string $segment, string $packageType, float $vatRate): array
    {
        if ($vatRate < 0) throw new RuntimeException('La tasa de IVA no es válida.');
        $operational = [
            'base' => $this->money($provider['costo'] ?? 0),
            'area_extendida' => $this->money($provider['costo_ae'] ?? 0),
            'kg_extra' => $this->money($provider['costo_kgs_extras'] ?? 0),
            'seguro' => $this->money($provider['costo_seguro'] ?? 0),
            'otros' => $this->money($provider['otros'] ?? 0),
        ];
        if (array_sum($operational) <= 0) throw new RuntimeException('El desglose operativo no contiene costos válidos.');
        $carrier = strtoupper((string) ($context['carrier'] ?? 'ESTAFETA'));
        $commercial = $applied = [];
        foreach (self::CONCEPTS as $concept) {
            $cost = $operational[$concept];
            $rule = $this->findRule($carrier, $service, $segment, $context['plan'] ?? null, $packageType, $concept);
            $profit = 0.0;
            if ($cost > 0 && $rule) {
                $profit = match ($rule->adjustment_type) {
                    'porcentaje' => $this->money($cost * ((float) $rule->value / 100)),
                    'monto_fijo' => $this->money($rule->value), 'sin_margen' => 0.0,
                    default => throw new RuntimeException('Tipo de ajuste comercial no válido.'),
                };
                $applied[$concept] = ['id' => $rule->id, 'name' => $rule->name, 'type' => $rule->adjustment_type];
            } elseif ($cost > 0 && $concept === 'base') {
                $legacy = app(ZigoPricingService::class)->calculate([
                    'carrier' => $carrier, 'customer_segment' => $segment, 'plan' => $context['plan'] ?? null,
                    'package_type' => $packageType, 'base_price' => $cost,
                    'crm_client_id' => $context['crm_client_id'] ?? null, 'api_client_id' => $context['api_client_id'] ?? null,
                    'user_id' => $context['user_id'] ?? null,
                ]);
                $profit = $this->money($legacy['final_price'] - $cost);
                $applied[$concept] = ['id' => $legacy['pricing_rule_id'], 'name' => $legacy['pricing_rule_name'], 'type' => 'legacy_base'];
            }
            $commercial[$concept] = $this->money($cost + $profit);
        }
        $subtotal = $this->money(array_sum($commercial));
        $vat = $this->money($subtotal * $vatRate);
        return ['operational_breakdown' => $operational, 'applied_rules' => $applied,
            'commercial_breakdown' => $commercial, 'commercial_subtotal' => $subtotal,
            'vat_rate' => $vatRate, 'vat' => $vat, 'customer_total' => $this->money($subtotal + $vat),
            'provider_control_total' => $this->money($provider['total'] ?? 0)];
    }

    private function findRule(string $carrier, string $service, string $segment, ?string $plan, string $packageType, string $concept): ?ZigoCommercialConceptRule
    {
        if (!Schema::hasTable('zigo_commercial_concept_rules')) return null;
        return ZigoCommercialConceptRule::query()->where('carrier', $carrier)->where('concept', $concept)->where('active', true)
            ->whereIn('service', [strtolower($service), 'all'])->whereIn('segment', [strtolower($segment), 'all'])
            ->whereIn('package_type', [strtolower($packageType), 'all'])
            ->where(fn ($q) => $q->whereNull('plan')->when($plan, fn ($p) => $p->orWhere('plan', $plan)))
            ->orderByRaw('CASE WHEN service = ? THEN 0 ELSE 1 END', [strtolower($service)])
            ->orderByRaw('CASE WHEN segment = ? THEN 0 ELSE 1 END', [strtolower($segment)])
            ->orderByRaw('CASE WHEN package_type = ? THEN 0 ELSE 1 END', [strtolower($packageType)])
            ->orderBy('priority')->orderBy('id')->get()->first(fn ($rule) => $rule->isAvailable());
    }

    private function money(mixed $value): float { return round(max(0, (float) $value), 2); }
}
