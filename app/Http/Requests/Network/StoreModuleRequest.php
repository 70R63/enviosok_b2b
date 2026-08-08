<?php
namespace App\Http\Requests\Network;
use App\Domain\Network\Catalog\Models\Module;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
class StoreModuleRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->hasRol('sysadmin') ?? false; }
    public function rules(): array { return [
        'code' => ['required','string','max:50','regex:/^[A-Z0-9_]+$/','unique:network_modules,code'],
        'name' => ['required','string','max:191'], 'description' => ['nullable','string'],
        'type' => ['required',Rule::in(Module::TYPES)], 'is_active' => ['nullable','boolean'],
        'sort_order' => ['required','integer','min:0','max:65535'],
    ]; }
}
