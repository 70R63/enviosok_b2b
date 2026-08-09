<?php
namespace App\Domain\Payments\Models;
use Illuminate\Database\Eloquent\Model;
final class TenantPaymentEvent extends Model{protected $fillable=['provider','event_key','payment_attempt_id','event_type','provider_payment_id','status','error_code','received_at','processed_at'];protected $casts=['received_at'=>'datetime','processed_at'=>'datetime'];}
