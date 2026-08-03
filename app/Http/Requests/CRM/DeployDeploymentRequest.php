<?php

namespace App\Http\Requests\CRM;

use Illuminate\Foundation\Http\FormRequest;

class DeployDeploymentRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->hasRol('sysadmin') === true; }
    public function rules(): array { return ['production_confirmation' => ['nullable', 'string', 'max:30']]; }
}
