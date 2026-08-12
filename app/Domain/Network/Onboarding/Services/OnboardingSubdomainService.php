<?php

namespace App\Domain\Network\Onboarding\Services;

use App\Domain\Network\Onboarding\Models\SaasOnboardingApplication;
use App\Domain\Network\Tenancy\Models\{Tenant, TenantDomain};
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class OnboardingSubdomainService
{
    public function normalize(string $label): string
    {
        $label = strtolower(trim($label));
        if (
            $label === '' || strlen($label) > 63 ||
            !preg_match('/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/', $label)
        ) {
            throw ValidationException::withMessages([
                'requested_subdomain' => 'Usa sólo letras minúsculas, números y guiones; sin guion inicial o final.',
            ]);
        }
        if (in_array($label, config('zigo_onboarding.reserved_subdomains', []), true)) {
            throw ValidationException::withMessages(['requested_subdomain' => 'El subdominio está reservado.']);
        }

        return $label;
    }

    public function hostname(string $label): string
    {
        $base = strtolower(trim((string) config('zigo_onboarding.subdomain_base')));
        $base = rtrim((string) parse_url(str_contains($base, '://') ? $base : '//'.$base, PHP_URL_HOST), '.');
        if ($base === '' || filter_var($base, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false) {
            throw ValidationException::withMessages(['requested_subdomain' => 'El dominio base no está configurado.']);
        }

        $suffix = strtolower(trim((string) config('zigo_onboarding.tenant_subdomain_suffix', '')));
        $finalLabel = $this->normalize($label).$suffix;
        if (
            strlen($finalLabel) > 63 ||
            !preg_match('/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/', $finalLabel)
        ) {
            throw ValidationException::withMessages([
                'requested_subdomain' => 'El sufijo del subdominio no está configurado correctamente.',
            ]);
        }

        return $finalLabel.'.'.$base;
    }

    public function reserve(SaasOnboardingApplication $application, string $requestedLabel): SaasOnboardingApplication
    {
        $label = $this->normalize($requestedLabel);
        $hostname = $this->hostname($label);
        $minutes = max(1, (int) config('zigo_onboarding.reservation_minutes', 60));

        try {
            return DB::transaction(function () use ($application, $label, $hostname, $minutes): SaasOnboardingApplication {
                $locked = SaasOnboardingApplication::query()
                    ->whereKey($application->id)->lockForUpdate()->firstOrFail();

                if (!in_array($locked->status, [
                    SaasOnboardingApplication::DRAFT,
                    SaasOnboardingApplication::PENDING_PAYMENT,
                ], true)) {
                    throw ValidationException::withMessages([
                        'requested_subdomain' => 'El onboarding ya no admite reservas de subdominio.',
                    ]);
                }

                if ($locked->reserved_subdomain_key === $label && $locked->subdomain_reserved_until?->isFuture()) {
                    return $locked;
                }
                if (Tenant::where('slug', $label)->exists() || TenantDomain::where('domain', $hostname)->exists()) {
                    throw ValidationException::withMessages(['requested_subdomain' => 'El subdominio no está disponible.']);
                }

                SaasOnboardingApplication::query()
                    ->where('reserved_subdomain_key', $label)
                    ->where('subdomain_reserved_until', '<=', now())
                    ->update(['reserved_subdomain_key' => null, 'subdomain_reserved_until' => null]);

                $holder = SaasOnboardingApplication::query()
                    ->where('reserved_subdomain_key', $label)
                    ->whereKeyNot($locked->id)
                    ->where('subdomain_reserved_until', '>', now())
                    ->exists();
                if ($holder) {
                    throw ValidationException::withMessages(['requested_subdomain' => 'El subdominio está reservado temporalmente.']);
                }

                $locked->update([
                    'requested_subdomain' => $label,
                    'reserved_subdomain_key' => $label,
                    'subdomain_reserved_until' => now()->addMinutes($minutes),
                    'lock_version' => $locked->lock_version + 1,
                ]);

                return $locked->fresh();
            });
        } catch (QueryException $exception) {
            $winner = SaasOnboardingApplication::query()
                ->where('reserved_subdomain_key', $label)
                ->where('subdomain_reserved_until', '>', now())->first();
            if ($winner?->is($application)) {
                return $winner;
            }
            if ($winner) {
                throw ValidationException::withMessages(['requested_subdomain' => 'El subdominio está reservado temporalmente.']);
            }
            throw $exception;
        }
    }
}
