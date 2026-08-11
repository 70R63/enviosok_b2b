<?php

namespace App\Domain\Network\Onboarding\Services;

use App\Domain\Network\Catalog\Models\{Module, Plan};
use App\Domain\Network\Commerce\Models\NetworkCommercialProduct;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class OnboardingCommercialSnapshotService
{
    public const VERSION = 1;

    public function build(
        Plan $plan,
        string $billingPeriod,
        array $selectedModuleIds = [],
        ?int $requestedOperations = null,
        string $taxRate = '0.00',
        ?string $planOfferUuid = null,
        ?string $operationOfferUuid = null,
    ): array {
        if (!in_array($billingPeriod, ['monthly', 'annual'], true)) {
            throw ValidationException::withMessages(['billing_period' => 'Periodo de facturación inválido.']);
        }
        if ($plan->status !== 'active') {
            throw ValidationException::withMessages(['selected_plan_id' => 'El plan seleccionado no está activo.']);
        }

        $billingType = $billingPeriod === 'annual' ? 'ANNUAL' : 'MONTHLY';
        $planOffer = $planOfferUuid ? NetworkCommercialProduct::query()
            ->where('uuid', $planOfferUuid)->where('type', 'PLAN')->where('plan_id', $plan->id)
            ->where('billing_type', $billingType)->where('is_active', true)->first() : null;
        if ($planOfferUuid && !$planOffer) {
            throw ValidationException::withMessages(['selected_plan_id' => 'La oferta del plan ya no está disponible.']);
        }
        $planAmount = $planOffer?->price
            ?? ($billingPeriod === 'annual' ? $plan->annual_price : $plan->monthly_price);
        if ($planAmount === null) {
            throw ValidationException::withMessages(['selected_plan_id' => 'El plan no tiene precio para el periodo.']);
        }
        $currency = strtoupper((string) ($planOffer?->currency ?? $plan->currency));

        $selectedModuleIds = collect($selectedModuleIds)->map(fn ($id) => (int) $id)->unique()->values()->all();
        $modules = Module::query()->whereIn('id', $selectedModuleIds)->where('is_active', true)->get();
        if ($modules->count() !== count($selectedModuleIds)) {
            throw ValidationException::withMessages(['selected_modules' => 'Uno o más módulos no están disponibles.']);
        }

        $includedIds = $plan->modules()->wherePivot('is_included', true)->pluck('network_modules.id');
        $moduleLines = $this->moduleLines($modules, $includedIds, $billingPeriod, $currency);
        $operationLine = $this->operationLine($requestedOperations, $operationOfferUuid, $currency);

        $subtotal = $this->add(
            (string) $planAmount,
            ...$moduleLines->pluck('amount')->all(),
            ...($operationLine ? [$operationLine['amount']] : []),
        );
        $taxAmount = $this->multiply($subtotal, $taxRate);
        $total = $this->add($subtotal, $taxAmount);

        return [
            'version' => self::VERSION,
            'generated_at' => now()->toIso8601String(),
            'plan' => [
                'id' => $plan->id,
                'code' => $plan->code,
                'name' => $plan->name,
                'billing_period' => $billingPeriod,
                'commercial_code' => $planOffer?->code,
                'amount' => $this->money((string) $planAmount),
            ],
            'modules' => $moduleLines->values()->all(),
            'operations' => $operationLine,
            'requested_operations' => $requestedOperations,
            'currency' => $currency,
            'subtotal' => $subtotal,
            'tax_rate' => $this->money($taxRate),
            'tax_amount' => $taxAmount,
            'total' => $total,
            'commercial_codes' => array_values(array_filter(array_merge(
                [$planOffer?->code ?? $plan->code],
                $moduleLines->pluck('commercial_code')->all(),
                [$operationLine['commercial_code'] ?? null],
            ))),
        ];
    }

    private function moduleLines(Collection $modules, Collection $includedIds, string $period, string $currency): Collection
    {
        return $modules->map(function (Module $module) use ($includedIds, $period, $currency): array {
            if ($includedIds->contains($module->id)) {
                return [
                    'id' => $module->id, 'code' => $module->code, 'name' => $module->name,
                    'included' => true, 'commercial_code' => null, 'amount' => '0.00',
                ];
            }

            $billingType = $period === 'annual' ? 'ANNUAL' : 'MONTHLY';
            $product = NetworkCommercialProduct::query()
                ->where('module_id', $module->id)->where('is_active', true)
                ->whereIn('type', ['MODULE', 'ADDON'])
                ->where('billing_type', $billingType)->orderBy('sort_order')->first();
            if (!$product) {
                throw ValidationException::withMessages([
                    'selected_modules' => "El módulo {$module->code} no tiene una oferta comercial activa.",
                ]);
            }
            if (strtoupper($product->currency) !== $currency) {
                throw ValidationException::withMessages(['selected_modules' => 'La moneda del módulo no coincide con el plan.']);
            }

            return [
                'id' => $module->id, 'code' => $module->code, 'name' => $module->name,
                'included' => false, 'commercial_code' => $product->code,
                'amount' => $this->money((string) $product->price),
            ];
        });
    }

    private function operationLine(?int $operations, ?string $offerUuid = null, ?string $currency = null): ?array
    {
        if ($operations === null) {
            return null;
        }
        if ($operations < 1) {
            throw ValidationException::withMessages(['requested_operations' => 'El volumen debe ser positivo.']);
        }

        $product = NetworkCommercialProduct::query()
            ->where('type', 'OPERATION_PACK')->where('billing_type', 'ONE_TIME')
            ->where('included_operations', $operations)->where('is_active', true)
            ->when($offerUuid, fn ($query) => $query->where('uuid', $offerUuid))
            ->orderBy('sort_order')->first();
        if (!$product) {
            throw ValidationException::withMessages([
                'requested_operations' => 'El volumen solicitado no tiene una oferta comercial activa.',
            ]);
        }
        if ($currency && strtoupper($product->currency) !== $currency) {
            throw ValidationException::withMessages(['requested_operations' => 'La moneda del volumen no coincide con el plan.']);
        }

        return [
            'quantity' => $operations,
            'commercial_code' => $product->code,
            'amount' => $this->money((string) $product->price),
        ];
    }

    private function add(string ...$amounts): string
    {
        $cents = array_sum(array_map(fn (string $amount) => $this->toCents($amount), $amounts));
        return $this->fromCents($cents);
    }

    private function multiply(string $amount, string $rate): string
    {
        if (!preg_match('/^(\d+)(?:\.(\d{1,4}))?$/', trim($rate), $matches)) {
            throw ValidationException::withMessages(['tax_rate' => 'Tasa de impuesto inválida.']);
        }
        $rateBasisPoints = ((int) $matches[1] * 10000)
            + (int) str_pad($matches[2] ?? '', 4, '0');
        $numerator = $this->toCents($amount) * $rateBasisPoints;
        return $this->fromCents(intdiv($numerator + 5000, 10000));
    }

    private function money(string $amount): string
    {
        return $this->fromCents($this->toCents($amount));
    }

    private function toCents(string $amount): int
    {
        if (!preg_match('/^(-?)(\d+)(?:\.(\d{1,2}))?$/', trim($amount), $matches)) {
            throw ValidationException::withMessages(['amount' => 'Importe monetario inválido.']);
        }
        $cents = ((int) $matches[2] * 100) + (int) str_pad($matches[3] ?? '', 2, '0');
        return ($matches[1] ?? '') === '-' ? -$cents : $cents;
    }

    private function fromCents(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';
        $cents = abs($cents);
        return $sign.intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
