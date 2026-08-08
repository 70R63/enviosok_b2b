<?php
namespace App\Domain\Network\Billing\Models;
use App\Domain\Network\Tenancy\Models\Tenant;use App\Models\User;use Illuminate\Database\Eloquent\Model;use Illuminate\Database\Eloquent\Relations\BelongsTo;
final class SubscriptionEvent extends Model{public$timestamps=false;protected$table='network_subscription_events';protected$fillable=['subscription_id','tenant_id','actor_user_id','event','from_status','to_status','metadata'];protected$casts=['metadata'=>'array','created_at'=>'datetime'];public function subscription():BelongsTo{return$this->belongsTo(Subscription::class);}public function tenant():BelongsTo{return$this->belongsTo(Tenant::class);}public function actor():BelongsTo{return$this->belongsTo(User::class,'actor_user_id');}}
