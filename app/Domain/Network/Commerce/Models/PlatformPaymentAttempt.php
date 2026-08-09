<?php
namespace App\Domain\Network\Commerce\Models;
use Illuminate\Database\Eloquent\Model; use Illuminate\Support\Str;
final class PlatformPaymentAttempt extends Model{protected$fillable=['tenant_id','saas_order_id','provider','status','provider_preference_id','provider_payment_id','external_reference','amount','currency','init_point','approved_at','rejected_at'];protected$hidden=['init_point'];protected$casts=['amount'=>'decimal:2','approved_at'=>'datetime','rejected_at'=>'datetime'];protected static function booted():void{self::creating(fn(self$m)=>$m->uuid??=(string)Str::uuid());}public function order(){return$this->belongsTo(TenantSaasOrder::class,'saas_order_id');}}
