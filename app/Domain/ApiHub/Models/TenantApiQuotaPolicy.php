<?php
namespace App\Domain\ApiHub\Models;use Illuminate\Database\Eloquent\Model;
final class TenantApiQuotaPolicy extends Model{protected $fillable=['tenant_id','monthly_request_limit','rate_limit_per_minute','valid_from','valid_until','source_saas_order_id'];protected $casts=['valid_from'=>'datetime','valid_until'=>'datetime'];public static function current(int$tenant):?self{return self::where('tenant_id',$tenant)->where('valid_from','<=',now())->where(fn($q)=>$q->whereNull('valid_until')->orWhere('valid_until','>=',now()))->latest('valid_from')->first();}}
