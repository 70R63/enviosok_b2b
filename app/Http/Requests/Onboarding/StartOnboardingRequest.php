<?php

namespace App\Http\Requests\Onboarding;

use Illuminate\Foundation\Http\FormRequest;

final class StartOnboardingRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'contact_name' => ['required', 'string', 'max:191'],
            'contact_last_name' => ['nullable', 'string', 'max:191'],
            'contact_email' => ['required', 'email:rfc', 'max:191'],
            'contact_phone' => ['nullable', 'string', 'max:40', 'regex:/^[0-9+() .-]+$/'],
            'company_name' => ['required', 'string', 'max:191'],
            'company_legal_name' => ['nullable', 'string', 'max:191'],
            'tax_id' => ['nullable', 'string', 'max:32', 'regex:/^[A-Za-z0-9&Ññ-]+$/'],
            'offer' => ['nullable', 'uuid'],
        ];
    }
}
