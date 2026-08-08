<?php
namespace App\Http\Requests\Network;
use App\Domain\Network\Tenancy\Models\Tenant;use Illuminate\Foundation\Http\FormRequest;use Illuminate\Validation\Rule;
final class UpdateTenantRequest extends FormRequest
{
 public function authorize():bool{return $this->user()?->hasRol('sysadmin')??false;}
 public function rules():array{$tenant=$this->route('tenant');return['name'=>['required','string','max:191'],'slug'=>['required','string','max:100','regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',Rule::unique('network_tenants','slug')->ignore($tenant)],'status'=>['required',Rule::in(Tenant::STATUSES)],'current_plan_id'=>['nullable','integer','exists:network_plans,id']];}
}
