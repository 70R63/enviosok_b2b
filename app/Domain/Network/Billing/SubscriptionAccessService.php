<?php
namespace App\Domain\Network\Billing;
use App\Domain\Network\Billing\Models\Subscription;use App\Domain\Network\Tenancy\Models\Tenant;
final class SubscriptionAccessService{public function __construct(private SubscriptionService$s){}public function isOperational(Tenant$t):bool{$sub=$this->s->currentForTenant($t);if(!$sub)return true;if(in_array($sub->status,['trial','active','past_due','grace'],true))return true;return$sub->status==='canceled'&&$sub->current_period_end->isFuture();}public function subscription(Tenant$t):?Subscription{return$this->s->currentForTenant($t);}}
