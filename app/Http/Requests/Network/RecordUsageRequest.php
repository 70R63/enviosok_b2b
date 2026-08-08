<?php
namespace App\Http\Requests\Network;
use Illuminate\Foundation\Http\FormRequest;use Illuminate\Validation\Rule;
final class RecordUsageRequest extends FormRequest{public function authorize():bool{return true;}public function rules():array{return['metric'=>['required',Rule::in(config('zigo_usage.metrics',[]))],'quantity'=>['required','integer','min:1','max:10000'],'idempotency_key'=>['nullable','string','max:191']];}}
