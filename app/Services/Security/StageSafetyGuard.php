<?php

namespace App\Services\Security;

use LogicException;

final class StageSafetyGuard
{
    public function enforce(): void
    {
        if (! app()->environment(['staging', 'stage'])) {
            return;
        }

        $errors = [];
        if ((bool) config('app.debug')) $errors[] = 'APP_DEBUG debe ser false.';
        if (! (bool) config('session.secure')) $errors[] = 'SESSION_SECURE_COOKIE debe ser true.';
        if (config('session.domain')) $errors[] = 'SESSION_DOMAIN debe permanecer vacío (cookies host-only).';
        if (in_array((string) config('cache.default'), ['array', 'null'], true)) $errors[] = 'CACHE_DRIVER debe ser persistente.';

        if ((bool) config('zigo_payments.providers.mercado_pago.enabled')) {
            $environment = strtolower((string) config('zigo_payments.providers.mercado_pago.environment'));
            if (! in_array($environment, ['sandbox', 'test'], true)) {
                $errors[] = 'Mercado Pago seller debe usar sandbox/test en Stage.';
            }
        }

        foreach ([
            'APP_URL' => config('app.url'),
            'ZIGO_DRIVER_URL' => config('zigo_driver.url'),
            'ZIGO_NETWORK_URL' => config('zigo_surfaces.network.url'),
            'ZIGO_PAYMENTS_URL' => config('zigo_surfaces.payments.url'),
            'ZIGO_API_URL' => config('zigo_api_hub.url'),
        ] as $name => $url) {
            if (strtolower((string) parse_url((string) $url, PHP_URL_SCHEME)) !== 'https') {
                $errors[] = $name.' debe usar HTTPS en Stage.';
            }
        }

        if ($errors !== []) {
            throw new LogicException('Stage safety gate: '.implode(' ', $errors));
        }
    }
}
