<?php

namespace App\Http\Requests\Tenant;

use App\Domain\Network\Tenancy\TenantAccessService;
use App\Http\Requests\Network\UpdateTenantBrandingRequest;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateTenantConfigurationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(TenantAccessService::class)->hasRole(['owner', 'admin'], $this->user());
    }

    public function rules(): array
    {
        return UpdateTenantBrandingRequest::brandingRules();
    }
}
