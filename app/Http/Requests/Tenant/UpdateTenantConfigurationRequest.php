<?php

namespace App\Http\Requests\Tenant;

use App\Domain\Network\Tenancy\TenantAccessService;
use App\Http\Requests\Network\UpdateTenantBrandingRequest;
use Illuminate\Foundation\Http\FormRequest;
use App\Domain\Network\ProductShell\TenantWorkspaceResolver;
use App\Domain\Network\Tenancy\TenantContext;

final class UpdateTenantConfigurationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(TenantAccessService::class)->hasRole(['owner', 'admin'], $this->user());
    }

    public function rules(): array
    {
        $rules = UpdateTenantBrandingRequest::brandingRules();
        try {
            $tenant = app(TenantContext::class)->tenant();
            if (app(TenantWorkspaceResolver::class)->resolveForPresentation($tenant)->value === 'zigo_ai') {
                unset($rules['landing_cards'], $rules['landing_cards.*.title'], $rules['landing_cards.*.description'], $rules['landing_cards.*.action'], $rules['hero_image']);
            }
        } catch (\Throwable) { /* preserve canonical validation when context is unavailable */ }
        return $rules;
    }

    public function messages(): array
    {
        return [
            'favicon.dimensions' => 'El favicon debe ser una imagen cuadrada de hasta 512 × 512 px.',
        ];
    }
}
