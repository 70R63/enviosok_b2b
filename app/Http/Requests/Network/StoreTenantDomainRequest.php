<?php
namespace App\Http\Requests\Network;
use App\Domain\Network\Tenancy\Models\TenantDomain;use Illuminate\Foundation\Http\FormRequest;use Illuminate\Validation\Rule;
final class StoreTenantDomainRequest extends FormRequest
{
 public function authorize():bool{return $this->user()?->hasRol('sysadmin')??false;}
 public function rules():array{return['domain'=>['required','string','max:253',Rule::unique('network_tenant_domains','domain'),function($a,$v,$fail){if(str_contains($v,'://')||strpbrk($v,'/?#@')!==false||filter_var($v,FILTER_VALIDATE_DOMAIN,FILTER_FLAG_HOSTNAME)===false)$fail('El dominio debe ser únicamente un hostname válido.');}],'type'=>['required',Rule::in(TenantDomain::TYPES)],'environment'=>['required',Rule::in(TenantDomain::ENVIRONMENTS)],'is_primary'=>['nullable','boolean'],'status'=>['required',Rule::in(TenantDomain::STATUSES)]];}
}
