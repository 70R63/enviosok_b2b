<?php
namespace App\Http\Requests\Network;
use App\Domain\Network\Tenancy\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
class StoreTenantRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->hasRol('sysadmin') ?? false; }
    public function rules(): array { return [
        'name' => ['required','string','max:191'],
        'slug' => ['required','string','max:100','regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/','unique:network_tenants,slug'],
        'status' => ['required',Rule::in(Tenant::STATUSES)],
    ]; }
}
