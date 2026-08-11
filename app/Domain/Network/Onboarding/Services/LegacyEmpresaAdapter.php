<?php

namespace App\Domain\Network\Onboarding\Services;

use App\Domain\Network\Onboarding\Exceptions\OnboardingProvisioningException;
use App\Domain\Network\Onboarding\Models\SaasOnboardingApplication;
use App\Models\{Empresa, User};

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

        if ($existingOwner) {
            if (!$existingOwner->empresa_id) {
                throw new OnboardingProvisioningException('OWNER_EMAIL_CONFLICT');
            }
            $company = Empresa::withoutGlobalScopes()->find($existingOwner->empresa_id);
            if (!$company || strtolower(trim((string) $company->email)) !== $application->contact_email) {
                throw new OnboardingProvisioningException('OWNER_EMAIL_CONFLICT');
            }
            return [$company, false];
        }

        if (Empresa::withoutGlobalScopes()->whereRaw('LOWER(email) = ?', [$application->contact_email])->exists()) {
            throw new OnboardingProvisioningException('LEGACY_COMPANY_CONFLICT');
        }

        $phone = substr(preg_replace('/\D+/', '', (string) $application->contact_phone), -10);
        $company = Empresa::withoutGlobalScopes()->create([
            'estatus' => 1,
            'contacto' => mb_substr(trim($application->contact_name.' '.$application->contact_last_name), 0, 50),
            'nombre' => mb_substr($application->company_legal_name ?: $application->company_name, 0, 50),
            'email' => $application->contact_email,
            // Legacy schema requires a non-null, max-10 value. Empty means not supplied; no fake number is invented.
            'telefono' => $phone,
        ]);

        return [$company, true];
    }
}
