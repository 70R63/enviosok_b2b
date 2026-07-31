<?php

namespace App\Services;

class ZigoDomainResolver
{
    public function currentPortal(?string $host = null): ?string
    {
        return $this->portalForHost($host ?? request()->getHost());
    }

    public function portalForHost(string $host): ?string
    {
        $normalizedHost = $this->normalizeHost($host);

        foreach ((array) config('zigo_domains.portals', []) as $portal => $domain) {
            if ($normalizedHost === ($domain['host'] ?? null)) {
                return (string) $portal;
            }
        }

        return null;
    }

    public function baseUrl(string $portal): ?string
    {
        $url = config("zigo_domains.portals.{$portal}.url");

        return is_string($url) && $url !== '' ? $url : null;
    }

    public function isSubdomainRoutingEnabled(): bool
    {
        return (bool) config('zigo_domains.routing_enabled', false);
    }

    private function normalizeHost(string $host): string
    {
        $host = trim($host);
        $parsedHost = parse_url(
            str_contains($host, '://') ? $host : '//' . $host,
            PHP_URL_HOST
        );

        return strtolower(rtrim((string) $parsedHost, '.'));
    }
}
