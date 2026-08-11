<?php

namespace App\Http\Requests\Onboarding;

use Illuminate\Foundation\Http\FormRequest;

final class ReserveOnboardingSubdomainRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return ['requested_subdomain' => ['required', 'string', 'max:63']];
    }
}
