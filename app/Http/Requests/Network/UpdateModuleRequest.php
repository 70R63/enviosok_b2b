<?php
namespace App\Http\Requests\Network;
use App\Domain\Network\Catalog\Models\Module;use Illuminate\Foundation\Http\FormRequest;use Illuminate\Validation\Rule;
final class UpdateModuleRequest extends FormRequest
{
 public function authorize():bool{return $this->user()?->hasRol('sysadmin')??false;}
 public function rules():array{$module=$this->route('module');return['code'=>['required','string','max:50','regex:/^[A-Z0-9_]+$/',Rule::unique('network_modules','code')->ignore($module)],'name'=>['required','string','max:191'],'description'=>['nullable','string'],'type'=>['required',Rule::in(Module::TYPES)],'is_active'=>['nullable','boolean'],'sort_order'=>['required','integer','min:0','max:65535']];}
}
