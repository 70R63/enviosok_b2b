<?php
namespace App\Http\Requests\Network;
use App\Domain\Network\Tenancy\Models\TenantDomain;use Illuminate\Foundation\Http\FormRequest;use Illuminate\Validation\Rule;
final class UpdateTenantDomainRequest extends FormRequest
{
 public function authorize():bool{return $this->user()?->hasRol('sysadmin')??false;}
 public function rules():array{return['type'=>['required',Rule::in(TenantDomain::TYPES)],'environment'=>['required',Rule::in(TenantDomain::ENVIRONMENTS)],'is_primary'=>['nullable','boolean'],'status'=>['required',Rule::in(TenantDomain::STATUSES)]];}
}
