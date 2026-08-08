<?php
namespace App\Http\Requests\Network;
use App\Domain\Network\Catalog\Models\Plan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
class StorePlanRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->hasRol('sysadmin') ?? false; }
    public function rules(): array { return [
        'code' => ['required','string','max:50','regex:/^[A-Z0-9_]+$/','unique:network_plans,code'],
        'name' => ['required','string','max:191'], 'description' => ['nullable','string'],
        'status' => ['required',Rule::in(Plan::STATUSES)],
        'monthly_price' => ['nullable','numeric','min:0'], 'annual_price' => ['nullable','numeric','min:0'],
        'currency' => ['required','string','size:3'], 'included_operations' => ['nullable','integer','min:0'],
    ]; }
}
