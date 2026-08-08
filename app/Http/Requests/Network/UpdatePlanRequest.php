<?php
namespace App\Http\Requests\Network;
use App\Domain\Network\Catalog\Models\Plan;use Illuminate\Foundation\Http\FormRequest;use Illuminate\Validation\Rule;
final class UpdatePlanRequest extends FormRequest
{
 public function authorize():bool{return $this->user()?->hasRol('sysadmin')??false;}
 public function rules():array{return['name'=>['required','string','max:191'],'description'=>['nullable','string'],'status'=>['required',Rule::in(Plan::STATUSES)],'monthly_price'=>['nullable','numeric','min:0'],'annual_price'=>['nullable','numeric','min:0'],'currency'=>['required','string','size:3'],'included_operations'=>['nullable','integer','min:0'],'modules'=>['required','array'],'modules.*.id'=>['required','integer','distinct','exists:network_modules,id'],'modules.*.included'=>['required','boolean'],'modules.*.limit_value'=>['nullable','integer','min:0']];}
}
