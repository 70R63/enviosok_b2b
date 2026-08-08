<?php

namespace App\Http\Requests\Network;

use App\Domain\Network\Tenancy\Models\TenantMembership;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreTenantMembershipRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'role' => ['required', Rule::in(TenantMembership::ROLES)],
            'status' => ['required', Rule::in(TenantMembership::STATUSES)],
        ];
    }
}
