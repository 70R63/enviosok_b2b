<?php

namespace App\Domain\Network\Billing;

use App\Domain\Network\Billing\Models\Subscription;
use App\Domain\Network\Commerce\Models\TenantSaasOrder;
use App\Models\ZigoNotificationDelivery;
use App\Notifications\SaasBillingLifecycleNotification;
use Illuminate\Support\Facades\Schema;

final class SaasBillingNotificationService
{
    public function send(Subscription $subscription, string $type, ?TenantSaasOrder $renewal = null): bool
    {
        if (!Schema::hasTable('zigo_notification_deliveries')) {
            return false;
        }
        $subscription->loadMissing(['plan','tenant.primaryDomain','tenant.memberships.user']);
        if (!$subscription->tenant?->primaryDomain) {
            return false;
        }

        $period = $subscription->current_period_end?->toDateString() ?? 'unknown';
        $key = 'saas-billing:'.$type.':'.$subscription->uuid.':'.$period;
        $recipients = $subscription->tenant->memberships->where('status','active')->whereIn('role',['owner','billing'])->pluck('user')->filter()->unique('id');
        if ($recipients->isEmpty()) {
            return false;
        }
        $delivery = ZigoNotificationDelivery::firstOrCreate(['event_key'=>$key], ['event_type'=>$type,'notifiable_type'=>Subscription::class,'notifiable_id'=>$subscription->id,'recipients'=>$recipients->pluck('email')->all(),'status'=>'PENDING','attempts'=>0]);
        if ($delivery->status === 'SENT') {
            return false;
        }

        $days = now()->startOfDay()->diffInDays($subscription->current_period_end->copy()->startOfDay(), false);
        $data = [
            'company' => $subscription->tenant->name,
            'plan' => $subscription->plan->name,
            'billing_period' => $subscription->current_period_start->diffInMonths($subscription->current_period_end) >= 10 ? 'Anual' : 'Mensual',
            'expiration' => $period,
            'days_remaining' => $days,
            'subtotal' => $renewal?->subtotal,
            'tax_amount' => $renewal?->tax_amount,
            'total' => $renewal?->total_amount,
            'currency' => $renewal?->currency,
            'payment_status' => $renewal?->payment_status,
            'new_period_end' => $type === 'RENEWAL_PAYMENT_CONFIRMED' ? $subscription->current_period_end?->toDateString() : null,
        ];
        $paymentUrl = $renewal && !in_array($type,['RENEWAL_PAYMENT_CONFIRMED','SUBSCRIPTION_REACTIVATED'],true)
            ? (string)config('zigo_onboarding.tenant_admin_scheme','https').'://'.$subscription->tenant->primaryDomain->domain.'/admin/compras/'.$renewal->uuid.'/pago'
            : null;

        foreach ($recipients as $user) {
            $user->notify(new SaasBillingLifecycleNotification($type, $data, $paymentUrl));
        }
        $delivery->update(['status'=>'SENT','sent_at'=>now(),'attempts'=>$delivery->attempts+1,'failed_at'=>null,'last_error'=>null]);
        return true;
    }
}
