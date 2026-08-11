<?php
namespace App\Domain\Network\Commerce\Models; use Illuminate\Database\Eloquent\Model;
final class PlatformPaymentEvent extends Model{protected$fillable=['provider','event_key','platform_payment_attempt_id','provider_payment_id','status','error_code','received_at','processed_at'];protected$casts=['received_at'=>'datetime','processed_at'=>'datetime'];public function attempt(){return$this->belongsTo(PlatformPaymentAttempt::class,'platform_payment_attempt_id');}}
