<?php
namespace App\Domain\Network\Commerce\Models;
use Illuminate\Database\Eloquent\Model; use Illuminate\Support\Str;
final class TenantOperationAllowance extends Model{protected$fillable=['tenant_id','subscription_id','saas_order_id','operations','starts_at','expires_at'];protected$casts=['operations'=>'integer','starts_at'=>'datetime','expires_at'=>'datetime'];protected static function booted():void{self::creating(fn(self$m)=>$m->uuid??=(string)Str::uuid());}}
