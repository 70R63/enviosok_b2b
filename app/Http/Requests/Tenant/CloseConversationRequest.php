<?php

namespace App\Http\Requests\Tenant;

use App\Domain\Network\Tenancy\TenantAccessService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class CloseConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(TenantAccessService::class)->canManageTenant($this->user());
    }

    public function rules(): array
    {
        return [];
    }

    public function withValidator(Validator $v): void
    {
        $v->after(function (Validator $v) {
            foreach (array_keys($this->all()) as $key) {
                if ($key !== '_token') {
                    $v->errors()->add($key, 'Este campo no está permitido.');
                }
            }
        });
    }
}
