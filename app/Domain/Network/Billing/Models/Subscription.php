<?php
namespace App\Domain\Network\Billing\Models;
use App\Domain\Network\Catalog\Models\Plan;use App\Domain\Network\Tenancy\Models\Tenant;use Illuminate\Database\Eloquent\Model;use Illuminate\Database\Eloquent\Relations\{BelongsTo,HasMany};use Illuminate\Support\Str;
final class Subscription extends Model
{
 public const STATUSES=['trial','trialing','active','past_due','grace','suspended','canceled','ended'];
 public const CURRENT_STATUSES=['trial','trialing','active','past_due','grace','suspended','canceled'];
 protected $table='network_subscriptions';protected $fillable=['tenant_id','plan_id','status','operations_limit','started_at','current_period_start','current_period_end','trial_ends_at','grace_ends_at','canceled_at','ended_at','provider_subscription_id','provider_plan_id','provider_status','next_payment_date','billing_frequency','cancel_at_period_end','pending_plan_id','pending_effective_at'];
 protected $casts=['operations_limit'=>'integer','pending_plan_id'=>'integer','started_at'=>'datetime','current_period_start'=>'datetime','current_period_end'=>'datetime','trial_ends_at'=>'datetime','grace_ends_at'=>'datetime','canceled_at'=>'datetime','ended_at'=>'datetime','next_payment_date'=>'datetime','pending_effective_at'=>'datetime','cancel_at_period_end'=>'boolean'];
 protected static function booted():void{static::creating(fn(self$s)=>$s->uuid??=(string)Str::uuid());}
 public function tenant():BelongsTo{return$this->belongsTo(Tenant::class);}public function plan():BelongsTo{return$this->belongsTo(Plan::class);}public function entitlements():HasMany{return$this->hasMany(Entitlement::class);}public function usageEvents():HasMany{return$this->hasMany(UsageEvent::class);}public function events():HasMany{return$this->hasMany(SubscriptionEvent::class);}
}
