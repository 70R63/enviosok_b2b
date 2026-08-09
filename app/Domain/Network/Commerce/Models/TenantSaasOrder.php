<?php
namespace App\Domain\Network\Commerce\Models;
use App\Domain\Network\Tenancy\Models\Tenant; use Illuminate\Database\Eloquent\Model; use Illuminate\Support\Str;
final class TenantSaasOrder extends Model{
 public const STATUSES=['DRAFT','PENDING_PAYMENT','PAID','ACTIVATED','EXPIRED','CANCELED','FAILED'];protected$fillable=['tenant_id','commercial_product_id','created_by_user_id','purchase_key','status','payment_status','quantity','unit_amount','subtotal','tax_amount','total_amount','currency','purchase_snapshot','payment_provider','payment_reference','expires_at','paid_at','activated_at'];protected$casts=['unit_amount'=>'decimal:2','subtotal'=>'decimal:2','tax_amount'=>'decimal:2','total_amount'=>'decimal:2','purchase_snapshot'=>'array','expires_at'=>'datetime','paid_at'=>'datetime','activated_at'=>'datetime'];protected static function booted():void{self::creating(fn(self$m)=>$m->uuid??=(string)Str::uuid());}public function tenant(){return$this->belongsTo(Tenant::class);}public function product(){return$this->belongsTo(NetworkCommercialProduct::class,'commercial_product_id');}public function attempts(){return$this->hasMany(PlatformPaymentAttempt::class,'saas_order_id');}
}
