<?php
namespace App\Http\Requests\Network;
use App\Domain\Network\Billing\Models\Subscription;use Illuminate\Foundation\Http\FormRequest;use Illuminate\Validation\Rule;
final class UpdateSubscriptionStatusRequest extends FormRequest{public function authorize():bool{return true;}public function rules():array{return['status'=>['required',Rule::in(Subscription::STATUSES)]];}}
