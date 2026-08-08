<?php

namespace App\Http\Requests\Network;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateTenantBrandingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRol('sysadmin') ?? false;
    }

    public function rules(): array
    {
        return self::validationRules();
    }

    public static function validationRules(): array
    {
        $hex = ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'];

        return ['brand_name' => ['nullable', 'string', 'max:191'], 'primary_color' => $hex, 'secondary_color' => $hex, 'accent_color' => $hex, 'support_email' => ['nullable', 'email', 'max:191'], 'support_phone' => ['nullable', 'string', 'max:50'], 'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'], 'favicon' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:512']];
    }
}
