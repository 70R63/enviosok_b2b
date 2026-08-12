<?php
namespace App\Domain\Network\Commerce\Models;
use App\Domain\Network\Catalog\Models\{Module,Plan}; use Illuminate\Database\Eloquent\Model; use Illuminate\Support\Str;
final class NetworkCommercialProduct extends Model{
 public const TYPES=['PLAN','MODULE','OPERATION_PACK','ADDON','DOMAIN','SERVICE']; public const BILLING_TYPES=['ONE_TIME','MONTHLY','ANNUAL']; protected $fillable=['code','name','description','type','billing_type','price','currency','module_id','plan_id','included_operations','is_active','is_public','sort_order','display_order','archived_at','metadata']; protected $casts=['price'=>'decimal:2','included_operations'=>'integer','is_active'=>'boolean','is_public'=>'boolean','display_order'=>'integer','archived_at'=>'datetime','metadata'=>'array']; protected static function booted():void{self::creating(fn(self$m)=>$m->uuid??=(string)Str::uuid());} public function plan(){return$this->belongsTo(Plan::class);}public function module(){return$this->belongsTo(Module::class);}
}
