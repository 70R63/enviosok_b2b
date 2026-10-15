<?php

namespace App\Domain\Network\Providers;

use Illuminate\Support\Facades\Validator;

final class ProviderPickupPreference
{
    /** Only application-controlled timezone and whitelisted fields are persisted. */
    public function normalize(array $input, string $dateKey = 'requested_date', bool $checkPast = true): array
    {
        $window = ($input['mode'] ?? null) === 'WINDOW';
        $data = Validator::make($input, [
            'mode' => 'required|in:ASAP,WINDOW',
            $dateKey => $window ? 'required|date_format:Y-m-d' : 'nullable',
            'window_start' => $window ? 'required|date_format:H:i' : 'nullable',
            'window_end' => $window ? 'required|date_format:H:i|after:window_start' : 'nullable',
        ])->validate();
        $timezone = (string) config('app.timezone');
        if ($window && $checkPast) {
            $now = now($timezone);
            Validator::make($data, [$dateKey => 'after_or_equal:'.$now->toDateString()])->validate();
            $end = \Carbon\CarbonImmutable::createFromFormat('!Y-m-d H:i', $data[$dateKey].' '.$data['window_end'], $timezone);
            if ($end->lessThanOrEqualTo($now)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['window_end' => 'La ventana de recolección ya terminó. Elige otra preferencia antes de pagar.']);
            }
        }

        return ['mode' => $data['mode'], $dateKey => $window ? $data[$dateKey] : null,
            'window_start' => $window ? $data['window_start'] : null,
            'window_end' => $window ? $data['window_end'] : null, 'timezone' => $timezone];
    }

    public function validateForPayment(\App\Models\B2cCotizacion $quote): void
    {
        if ($quote->provider !== 'tenant_provider_local' || $quote->hasAccreditedPayment() || $quote->hasGeneratedGuide()) return;
        $this->normalize((array) data_get($quote->guia_request_snapshot, 'pickup_request', []));
    }

    public function label(?array $data): string
    {
        if (! $data) return 'Sin preferencia registrada';
        if (($data['mode'] ?? null) === 'ASAP') return 'Lo antes posible';
        $date = $data['confirmed_date'] ?? $data['requested_date'] ?? null;
        return $date ? \Carbon\Carbon::parse($date)->format('d/m/Y').' · '.$data['window_start'].'–'.$data['window_end'].' ('.$data['timezone'].')' : 'Sin ventana registrada';
    }
}
