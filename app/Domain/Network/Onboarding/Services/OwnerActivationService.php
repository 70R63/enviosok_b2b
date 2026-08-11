<?php

namespace App\Domain\Network\Onboarding\Services;

use App\Domain\Network\Onboarding\Models\SaasOnboardingApplication;
use App\Models\ZigoNotificationDelivery;
use App\Notifications\SaasOwnerWelcomeNotification;
use Illuminate\Support\Facades\{DB, Log, Password, Schema};
use Throwable;

final class OwnerActivationService
{
    public function sendIfNeeded(SaasOnboardingApplication|int $application): void
    {
        if (!Schema::hasTable('zigo_notification_deliveries')) return;

        $application = $application instanceof SaasOnboardingApplication
            ? $application->fresh() : SaasOnboardingApplication::find($application);
        if (!$application || $application->status !== SaasOnboardingApplication::ACTIVE) return;
        if ($application->owner_is_new && !Schema::hasTable((string) config('auth.passwords.users.table'))) return;

        $owner = $application->owner;
        $domain = $application->tenant?->domains()->where('is_primary', true)
            ->where('status', 'verified')->first();
        if (!$owner || !$domain) return;

        $eventKey = 'saas-owner-welcome:'.$application->uuid;
        $claimed = DB::transaction(function () use ($application, $owner, $eventKey): bool {
            $delivery = ZigoNotificationDelivery::firstOrCreate(
                ['event_key' => $eventKey],
                [
                    'event_type' => 'SAAS_OWNER_WELCOME',
                    'notifiable_type' => SaasOnboardingApplication::class,
                    'notifiable_id' => $application->id,
                    'recipients' => [$owner->email], 'status' => 'PENDING', 'attempts' => 0,
                ],
            );
            $delivery = ZigoNotificationDelivery::whereKey($delivery->id)->lockForUpdate()->firstOrFail();
            if ($delivery->status === 'SENT') return false;
            if ($delivery->status === 'PROCESSING' && $delivery->updated_at?->isAfter(now()->subMinutes(10))) return false;
            $delivery->update(['status' => 'PROCESSING', 'attempts' => $delivery->attempts + 1, 'last_error' => null]);
            return true;
        });
        if (!$claimed) return;

        try {
            $scheme = (string) config('zigo_onboarding.tenant_admin_scheme', 'https');
            $adminUrl = $scheme.'://'.$domain->domain.'/admin';
            $actionUrl = $adminUrl.'/login';
            $expires = (int) config('auth.passwords.users.expire', 60);
            if ($application->owner_is_new) {
                $token = Password::broker()->createToken($owner);
                $actionUrl = $adminUrl.'/activate/'.$application->public_token.'/'.$token;
            }
            $owner->notify(new SaasOwnerWelcomeNotification(
                $application->company_name, $adminUrl, $actionUrl,
                (bool) $application->owner_is_new, $expires,
            ));

            DB::transaction(function () use ($application, $eventKey): void {
                ZigoNotificationDelivery::where('event_key', $eventKey)->update([
                    'status' => 'SENT', 'sent_at' => now(), 'failed_at' => null, 'last_error' => null,
                ]);
                $locked = SaasOnboardingApplication::whereKey($application->id)->lockForUpdate()->firstOrFail();
                if (!$locked->owner_activation_sent_at) {
                    $locked->update(['owner_activation_sent_at' => now()]);
                    $locked->events()->create([
                        'from_status' => $locked->status, 'to_status' => $locked->status,
                        'event' => 'OWNER_ACTIVATION_SENT', 'actor_type' => 'system',
                        'correlation_key' => $eventKey, 'metadata_json' => null, 'created_at' => now(),
                    ]);
                }
            });
        } catch (Throwable $exception) {
            ZigoNotificationDelivery::where('event_key', $eventKey)->update([
                'status' => 'FAILED', 'failed_at' => now(),
                'last_error' => 'OWNER_WELCOME_DELIVERY_FAILED',
            ]);
            Log::warning('Owner onboarding welcome delivery failed', ['onboarding_uuid' => $application->uuid]);
        }
    }
}
