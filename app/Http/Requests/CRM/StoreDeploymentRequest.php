<?php

namespace App\Http\Requests\CRM;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDeploymentRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->hasRol('sysadmin') === true; }
    public function rules(): array
    {
        return [
            'environment' => ['required', Rule::in(['stage', 'production'])],
            'package' => ['required', 'file', 'mimes:zip', 'max:' . (max(1, (int) config('zigo_devops.max_package_mb', 25)) * 1024)],
        ];
    }
}
