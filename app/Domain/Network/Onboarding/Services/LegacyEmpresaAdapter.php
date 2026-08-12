<?php

namespace App\Domain\Network\Onboarding\Services;

use App\Domain\Network\Onboarding\Exceptions\OnboardingProvisioningException;
use App\Domain\Network\Onboarding\Models\SaasOnboardingApplication;
use App\Models\{Empresa, User};
use Illuminate\Support\Str;

final class LegacyEmpresaAdapter
{
    public function resolveOrCreate(SaasOnboardingApplication $application, ?User $existingOwner): array
    {
        if ($application->legacy_empresa_id) {
            $company = Empresa::withoutGlobalScopes()->find($application->legacy_empresa_id);
            if (!$company) {
                throw new OnboardingProvisioningException('LEGACY_COMPANY_MISSING');
            }
            return [$company, false];
        }

        $historicalCompany = $existingOwner?->empresa_id
            ? Empresa::withoutGlobalScopes()->find($existingOwner->empresa_id)
            : null;
        if ($historicalCompany && $this->representsOnboardingCompany($historicalCompany, $application)) {
            return [$historicalCompany, false];
        }

        $identityCollision = Empresa::withoutGlobalScopes()->get()->first(
            fn (Empresa $company): bool => (!$historicalCompany || !$company->is($historicalCompany))
                && $this->companyNameMatches($company, $application),
        );
        if ($identityCollision) {
            throw new OnboardingProvisioningException('LEGACY_COMPANY_CONFLICT');
        }

        $emailInUse = Empresa::withoutGlobalScopes()
            ->whereRaw('LOWER(email) = ?', [strtolower(trim($application->contact_email))])
            ->first();
        if ($emailInUse && !$existingOwner) {
            throw new OnboardingProvisioningException('LEGACY_COMPANY_CONFLICT');
        }

        $phone = substr(preg_replace('/\D+/', '', (string) $application->contact_phone), -10);
        $company = Empresa::withoutGlobalScopes()->create([
            'estatus' => 1,
            'contacto' => mb_substr(trim($application->contact_name.' '.$application->contact_last_name), 0, 50),
            'nombre' => mb_substr($application->company_legal_name ?: $application->company_name, 0, 50),
            // A legacy company email is not a SaaS ownership key. Avoid taking an email already
            // used by the existing owner's historical company; the User keeps that identity.
            'email' => $emailInUse ? null : $application->contact_email,
            // Legacy schema requires a non-null, max-10 value. Empty means not supplied; no fake number is invented.
            'telefono' => $phone,
        ]);

        return [$company, true];
    }

    private function representsOnboardingCompany(
        Empresa $company,
        SaasOnboardingApplication $application,
    ): bool {
        if (!$this->companyNameMatches($company, $application)) {
            return false;
        }

        $companyEmail = strtolower(trim((string) $company->email));
        return $companyEmail === '' || $companyEmail === strtolower(trim($application->contact_email));
    }

    private function companyNameMatches(Empresa $company, SaasOnboardingApplication $application): bool
    {
        $companyName = $this->normalizeName((string) $company->nombre);
        $onboardingNames = array_filter([
            $this->normalizeName((string) $application->company_name),
            $this->normalizeName((string) $application->company_legal_name),
        ]);

        return $companyName !== '' && in_array($companyName, $onboardingNames, true);
    }

    private function normalizeName(string $name): string
    {
        return preg_replace('/[^a-z0-9]+/', '', Str::lower(Str::ascii(trim($name)))) ?? '';
    }
}
