<?php

namespace App\Http\Requests\Tenant;

use App\Domain\Network\Tenancy\Models\TenantMembership;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateTenantMembershipRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'role' => ['required', Rule::in(TenantMembership::ROLES)],
            'status' => ['required', Rule::in(TenantMembership::STATUSES)],
        ];
    }
}
