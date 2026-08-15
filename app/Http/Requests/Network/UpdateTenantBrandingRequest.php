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
        return self::brandingRules();
    }

    public static function brandingRules(): array
    {
        $hex = ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'];

        return ['brand_name' => ['nullable', 'string', 'max:191'], 'tagline' => ['nullable','string','max:120'], 'primary_color' => $hex, 'secondary_color' => $hex, 'accent_color' => $hex, 'support_email' => ['nullable', 'email', 'max:191'], 'support_phone' => ['nullable', 'string', 'max:50'], 'landing_cards'=>['nullable','array','size:3'], 'landing_cards.*.title'=>['required','string','max:80'], 'landing_cards.*.description'=>['required','string','max:240'], 'landing_cards.*.action'=>['required',\Illuminate\Validation\Rule::in(['QUOTE','REGISTER','TRACKING','NONE'])], 'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'], 'hero_image' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:4096'], 'favicon' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:512', 'dimensions:max_width=512,max_height=512,ratio=1/1']];
    }
}
