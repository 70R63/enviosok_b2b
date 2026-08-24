<?php
namespace App\Http\Requests\Tenant;
use Illuminate\Foundation\Http\FormRequest;
final class UpdateAgentContractActionsRequest extends FormRequest
{
 public function authorize():bool{return true;}
 public function rules():array{return['allowed_actions'=>['sometimes','array','max:20'],'allowed_actions.*'=>['string','max:64','distinct']];}
 public function actions():array{return$this->validated('allowed_actions',[]);}
}
