<?php

namespace App\Http\Requests\Onboarding;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SelectOnboardingSolutionRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'plan_offer' => ['required', 'uuid'],
            'billing_period' => ['required', Rule::in(['monthly', 'annual'])],
            'module_offers' => ['nullable', 'array', 'max:30'],
            'module_offers.*' => ['required', 'uuid', 'distinct'],
            'operations_offer' => ['nullable', 'uuid'],
        ];
    }
}
