<?php
namespace App\Domain\Network\Billing\Models;
use App\Domain\Network\Catalog\Models\Plan;use App\Domain\Network\Tenancy\Models\Tenant;use Illuminate\Database\Eloquent\Model;use Illuminate\Database\Eloquent\Relations\{BelongsTo,HasMany};use Illuminate\Support\Str;
final class Subscription extends Model
{
 public const STATUSES=['trial','active','past_due','grace','suspended','canceled','ended'];
 public const CURRENT_STATUSES=['trial','active','past_due','grace','suspended','canceled'];
 protected $table='network_subscriptions';protected $fillable=['tenant_id','plan_id','status','operations_limit','started_at','current_period_start','current_period_end','trial_ends_at','grace_ends_at','canceled_at','ended_at'];
 protected $casts=['operations_limit'=>'integer','started_at'=>'datetime','current_period_start'=>'datetime','current_period_end'=>'datetime','trial_ends_at'=>'datetime','grace_ends_at'=>'datetime','canceled_at'=>'datetime','ended_at'=>'datetime'];
 protected static function booted():void{static::creating(fn(self$s)=>$s->uuid??=(string)Str::uuid());}
 public function tenant():BelongsTo{return$this->belongsTo(Tenant::class);}public function plan():BelongsTo{return$this->belongsTo(Plan::class);}public function entitlements():HasMany{return$this->hasMany(Entitlement::class);}public function usageEvents():HasMany{return$this->hasMany(UsageEvent::class);}public function events():HasMany{return$this->hasMany(SubscriptionEvent::class);}
}
