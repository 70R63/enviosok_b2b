<?php

namespace App\Services\ApiHub\Webhooks;

use InvalidArgumentException;

class WebhookUrlGuard
{
    public function assertAllowed(
        string $url,
        string $environment
    ): void {
        $parts = parse_url($url);

        if (! is_array($parts)) {
            throw new InvalidArgumentException(
                'La URL webhook no es válida.'
            );
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));

        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new InvalidArgumentException(
                'La URL webhook debe utilizar HTTP o HTTPS.'
            );
        }

        if ($host === '') {
            throw new InvalidArgumentException(
                'La URL webhook no contiene un host válido.'
            );
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new InvalidArgumentException(
                'La URL webhook no puede contener credenciales.'
            );
        }

        if ($environment === 'production' && $scheme !== 'https') {
            throw new InvalidArgumentException(
                'Los webhooks de producción requieren HTTPS.'
            );
        }

        if ($this->privateUrlsAllowed()) {
            return;
        }

        if ($host === 'localhost' || str_ends_with($host, '.localhost')) {
            throw new InvalidArgumentException(
                'La URL webhook no puede apuntar a localhost.'
            );
        }

        $addresses = $this->resolveAddresses($host);

        if ($addresses === []) {
            throw new InvalidArgumentException(
                'No fue posible resolver el host del webhook.'
            );
        }

        foreach ($addresses as $address) {
            if (! $this->isPublicAddress($address)) {
                throw new InvalidArgumentException(
                    'La URL webhook apunta a una red privada o reservada.'
                );
            }
        }
    }

    private function privateUrlsAllowed(): bool
    {
        return (app()->environment('local') || app()->environment('testing'))
            || (bool) config(
                'services.zigo_webhooks.allow_private_urls',
                false
            );
    }

    private function resolveAddresses(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }

        $addresses = [];

        if (function_exists('dns_get_record')) {
            $records = @dns_get_record($host, DNS_A | DNS_AAAA);

            if (is_array($records)) {
                foreach ($records as $record) {
                    if (! empty($record['ip'])) {
                        $addresses[] = $record['ip'];
                    }

                    if (! empty($record['ipv6'])) {
                        $addresses[] = $record['ipv6'];
                    }
                }
            }
        }

        if ($addresses === []) {
            $ipv4 = @gethostbynamel($host);

            if (is_array($ipv4)) {
                $addresses = array_merge($addresses, $ipv4);
            }
        }

        return array_values(array_unique($addresses));
    }

    private function isPublicAddress(string $address): bool
    {
        return filter_var(
            $address,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) !== false;
    }
}
