<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustHosts as Middleware;

class TrustHosts extends Middleware
{
    /**
     * Always refresh Symfony's process-wide trusted-host state.
     *
     * Long-running workers and test processes may serve more than one ZIGO
     * surface. Reapplying the current environment's contract prevents a
     * previous request from leaving a narrower or broader host policy behind.
     */
    protected function shouldSpecifyTrustedHosts()
    {
        return true;
    }

    /**
     * Get the host patterns that should be trusted.
     *
     * @return array<int, string|null>
     */
    public function hosts()
    {
        if (app()->environment(['local', 'testing'])) {
            return ['^.*$'];
        }

        $patterns = array_filter([
            $this->allSubdomainsOfApplicationUrl(),
            ...array_map(
                static fn (string $host): string => '^'.preg_quote($host, '/').'$' ,
                array_filter([
                    (string) config('zigo_driver.host'),
                    (string) config('zigo_surfaces.network.host'),
                    (string) config('zigo_surfaces.payments.host'),
                    (string) config('zigo_api_hub.host'),
                ])
            ),
            ...array_map(
                static fn (string $host): string => '^'.preg_quote(trim($host), '/').'$' ,
                (array) config('zigo_security.trusted_hosts', [])
            ),
            ...array_map(
                static fn (string $domain): string => '^(.+\\.)?'.preg_quote(ltrim(trim($domain), '.'), '/').'$' ,
                (array) config('zigo_security.trusted_base_domains', [])
            ),
        ]);

        return array_values(array_unique($patterns));
    }
}
