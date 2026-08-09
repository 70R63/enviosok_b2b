<?php
namespace App\Domain\Payments\Models;
use App\Domain\Network\Channels\B2C\Models\{TenantCustomerCheckout,TenantCustomerProfile}; use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\Relations\BelongsTo; use Illuminate\Support\Str;
final class TenantPaymentAttempt extends Model{
 protected $fillable=['tenant_id','customer_profile_id','checkout_id','payment_connection_id','provider','status','provider_preference_id','provider_payment_id','external_reference','amount','currency','marketplace_fee_amount','init_point','approved_at','rejected_at']; protected $hidden=['init_point']; protected $casts=['amount'=>'decimal:2','marketplace_fee_amount'=>'decimal:2','approved_at'=>'datetime','rejected_at'=>'datetime'];
 protected static function booted():void{self::creating(fn(self $m)=>$m->uuid??=(string)Str::uuid());} public function checkout():BelongsTo{return $this->belongsTo(TenantCustomerCheckout::class);} public function connection():BelongsTo{return $this->belongsTo(TenantPaymentConnection::class,'payment_connection_id');}
}
