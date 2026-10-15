<?php
namespace App\Services\Shipping;

use App\Mail\GuideRecoveryMail;
use App\Models\B2cCotizacion;
use App\Models\B2cGuideRecoveryAudit;
use App\Models\B2cGuideRecoveryCase;
use App\Models\B2cGuideRecoveryToken;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

final class GuideRecoveryService
{
    public const CREATION_FAILED='CREATION_FAILED', DOCUMENT_MISSING='DOCUMENT_MISSING', QUOTE_EXPIRED='QUOTE_EXPIRED', MAX_ATTEMPTS='MAX_ATTEMPTS';

    public function classification(B2cCotizacion $q): ?string
    {
        if (!$q->hasAccreditedPayment()) return null;
        if ($q->quote_expires_at?->isPast() && !$q->guia_id && !$q->tracking_number) return self::QUOTE_EXPIRED;
        if (!$q->guia_id && !$q->tracking_number && (int)$q->guia_generation_attempts >= (int)config('zigo_b2c_xperta.guide_max_attempts',3)) return self::MAX_ATTEMPTS;
        if (($q->guia_id || $q->tracking_number) && !$q->documento) return self::DOCUMENT_MISSING;
        if (!$q->guia_id && !$q->tracking_number && (strtoupper((string)$q->guia_estatus)==='ERROR_PROVEEDOR' || $q->guia_last_error_code)) return self::CREATION_FAILED;
        return null;
    }

    public function syncCase(B2cCotizacion $q): ?B2cGuideRecoveryCase
    {
        $classification=$this->classification($q); if(!$classification)return null;
        return B2cGuideRecoveryCase::updateOrCreate(['cotizacion_id'=>$q->id],[
            'classification'=>$classification,'status'=>'OPEN','original_paid_amount'=>$q->payment_verified_amount ?: $q->precio,
        ]);
    }

    public function issue(B2cCotizacion $q, string $email, string $mailKind='pending'): string
    {
        if ($q->provider === 'tenant_provider_local') return $this->sendProviderConfirmation($q, $email);
        $plain=Str::random(80); $normalized=Str::lower(trim($email));
        $token = B2cGuideRecoveryToken::create(['cotizacion_id'=>$q->id,'email_hash'=>hash('sha256',$normalized),'token_hash'=>hash('sha256',$plain),'expires_at'=>now()->addHours((int)config('zigo_guide_recovery.token_hours',48)),'max_attempts'=>(int)config('zigo_guide_recovery.max_attempts',10)]);
        $this->audit($q, 'TOKEN_ISSUED', 'SUCCESS', ['token_id' => $token->id]);

        try {
            Mail::to($normalized)->send(new GuideRecoveryMail($q->fresh(), $plain, $mailKind));
        } catch (Throwable $e) {
            $token->forceFill(['revoked_at' => now()])->save();
            $this->audit($q, 'MAIL_FAILED', 'FAILURE', ['exception' => class_basename($e)]);
            Log::warning('No fue posible enviar correo de recuperación',['cotizacion_id'=>$q->id,'kind'=>$mailKind,'exception'=>get_class($e)]);
            throw new \RuntimeException('RECOVERY_MAIL_FAILED', 0, $e);
        }

        B2cGuideRecoveryToken::where('cotizacion_id',$q->id)->where('id','<>',$token->id)->whereNull('revoked_at')->update(['revoked_at'=>now()]);
        $column=$this->mailTimestampColumn($mailKind);
        B2cCotizacion::whereKey($q->id)->update([$column=>now()]);
        $this->audit($q, 'MAIL_SENT', 'SUCCESS', ['token_id' => $token->id]);
        $this->audit($q,'ISSUE_LINK','SUCCESS');
        return $plain;
    }

    /**
     * Opens the recovery path after a paid guide creation failed.  This method is
     * deliberately safe to call from both the payment return and the webhook.
     */
    public function recoverCreationFailure(B2cCotizacion $q, string $email): ?string
    {
        $normalized = Str::lower(trim($email));
        if ($normalized === '' || !$q->hasAccreditedPayment() || $q->guia_id || $q->tracking_number) {
            return null;
        }

        $plain = null;
        $token = DB::transaction(function () use ($q, $normalized, &$plain) {
            $locked = B2cCotizacion::query()->whereKey($q->id)->lockForUpdate()->firstOrFail();
            if (!$locked->hasAccreditedPayment() || $locked->guia_id || $locked->tracking_number) return null;

            B2cGuideRecoveryCase::updateOrCreate(['cotizacion_id' => $locked->id], [
                'classification' => self::CREATION_FAILED,
                'status' => 'OPEN',
                'original_paid_amount' => $locked->payment_verified_amount ?: $locked->precio,
            ]);

            $active = B2cGuideRecoveryToken::query()
                ->where('cotizacion_id', $locked->id)->whereNull('revoked_at')
                ->where('expires_at', '>', now())->whereColumn('attempts', '<', 'max_attempts')
                ->latest('id')->first();
            if (!$active) {
                $plain = Str::random(80);
                $active = B2cGuideRecoveryToken::create([
                    'cotizacion_id' => $locked->id,
                    'email_hash' => hash('sha256', $normalized),
                    'token_hash' => hash('sha256', $plain),
                    'expires_at' => now()->addHours((int) config('zigo_guide_recovery.token_hours', 48)),
                    'max_attempts' => (int) config('zigo_guide_recovery.max_attempts', 10),
                ]);
            }

            if (!B2cGuideRecoveryAudit::where('cotizacion_id', $locked->id)
                ->where('action', 'CREATION_FAILED_RECOVERY_OPENED')->where('result', 'SUCCESS')->exists()) {
                $this->audit($locked, 'CREATION_FAILED_RECOVERY_OPENED', 'SUCCESS');
            }
            return $active;
        });

        if (!$token) return null;
        // A repeated callback cannot reconstruct an existing plaintext token.  The
        // first caller owns delivery; later callers observe the claimed timestamp.
        if ($plain !== null && !$this->sendOnce($q->fresh(), $normalized, $plain, 'pending')) {
            $token->forceFill(['revoked_at' => now()])->save();
        }
        return $plain;
    }

    public function guideAvailable(B2cCotizacion $q, string $email): void
    {
        if ($q->provider === 'tenant_provider_local') {
            $this->providerFulfillmentAvailable($q, $email);
            return;
        }
        if (!$q->guia_id && !$q->tracking_number && !$q->documento) return;
        B2cGuideRecoveryCase::where('cotizacion_id', $q->id)->update(['status' => 'RESOLVED']);
        if ($q->fresh()->guide_generated_email_sent_at !== null) return;
        $this->issue($q, $email, 'generated');
    }

    private function providerFulfillmentAvailable(B2cCotizacion $q, string $email): void
    {
        $bridge = \App\Domain\Network\Providers\Models\ProviderFulfillment::where('provider_contract_id', $q->providerContract?->id)->first();
        if (! $bridge || ! filter_var($email, FILTER_VALIDATE_EMAIL)) return;
        // Reuse the existing B2C notification claim and configured Laravel mailer.
        $claimTime = now();
        $claimed = B2cCotizacion::whereKey($q->id)->whereNull('guide_generated_email_sent_at')
            ->where(fn ($query) => $query->whereNull('provider_confirmation_claimed_at')->orWhere('provider_confirmation_claimed_at', '<=', now()->subMinutes(10)))
            ->update(['provider_confirmation_claimed_at' => $claimTime]);
        if ($claimed !== 1) return;
        try {
            $this->sendProviderConfirmation($q, $email);
            B2cCotizacion::whereKey($q->id)->where('provider_confirmation_claimed_at', $claimTime)
                ->update(['guide_generated_email_sent_at' => now(), 'provider_confirmation_claimed_at' => null]);
        } catch (Throwable $exception) {
            B2cCotizacion::whereKey($q->id)->where('provider_confirmation_claimed_at', $claimTime)->update(['provider_confirmation_claimed_at' => null]);
            Log::warning('provider_fulfillment.mail_pending', ['quote_id' => $q->id, 'exception' => get_class($exception)]);
        }
    }

    private function sendProviderConfirmation(B2cCotizacion $q, string $email): string
    {
        $bridge = \App\Domain\Network\Providers\Models\ProviderFulfillment::where('provider_contract_id', $q->providerContract?->id)->first();
        if (! $bridge || ! filter_var($email, FILTER_VALIDATE_EMAIL)) throw new \DomainException('LOCAL_GUIDE_NOT_AVAILABLE');
        $shipment = $bridge->shipment;
        $domain = \App\Domain\Network\Tenancy\Models\TenantDomain::where('tenant_id', $bridge->consumer_tenant_id)->where('status', 'verified')->orderBy('id')->value('domain');
        $root = $domain ? 'https://'.$domain : rtrim((string) (config('zigo_domains.portals.b2c.url') ?: config('app.url')), '/');
        $plain = '';
        $token = null;
        if ($q->user_id === null) {
            $plain = Str::random(80);
            $token = B2cGuideRecoveryToken::create(['cotizacion_id' => $q->id, 'email_hash' => hash('sha256', Str::lower(trim($email))),
                'token_hash' => hash('sha256', $plain), 'expires_at' => now()->addHours((int) config('zigo_guide_recovery.token_hours', 48)),
                'max_attempts' => (int) config('zigo_guide_recovery.max_attempts', 10)]);
        }
        try {
            Mail::to($email)->send(new \App\Mail\ProviderFulfillmentConfirmedMail([
                'folio' => $shipment->tracking_number, 'service' => $shipment->guide_snapshot['service_name'], 'provider' => app(\App\Domain\Network\Providers\ProviderPresentation::class)->snapshotIdentity($shipment->guide_snapshot)['display_name'],
                'pickup' => \App\Support\Presentation\AddressPresenter::lines($shipment->sender_snapshot),
                'delivery' => \App\Support\Presentation\AddressPresenter::lines($shipment->recipient_snapshot),
                'url' => $root.($token ? '/envio/recuperar/'.$plain : '/b2c/envio-local/'.$q->providerContract->uuid),
            ]));
        } catch (Throwable $exception) {
            $token?->forceFill(['revoked_at' => now()])->save();
            throw $exception;
        }
        return $plain;
    }

    public function resolve(string $plain): ?B2cGuideRecoveryToken
    {
        $token=B2cGuideRecoveryToken::where('token_hash',hash('sha256',$plain))->first();
        if ($token?->cotizacion?->provider === 'tenant_provider_local') {
            return DB::transaction(function () use ($token): ?B2cGuideRecoveryToken {
                $locked = B2cGuideRecoveryToken::whereKey($token->id)->lockForUpdate()->first();
                if (! $locked || $locked->revoked_at || $locked->expires_at->isPast() || $locked->attempts >= $locked->max_attempts) return null;
                // Legacy TIMESTAMP may have implicit ON UPDATE semantics. Assign the
                // original expiry explicitly while atomically counting a local read.
                DB::table('b2c_guide_recovery_tokens')->where('id', $locked->id)->update([
                    'attempts' => $locked->attempts + 1, 'last_used_at' => now(), 'updated_at' => now(),
                    'expires_at' => $locked->expires_at,
                ]);
                $locked->refresh();
                $this->audit($locked->cotizacion, 'OPEN_LINK', 'SUCCESS');
                return $locked;
            });
        }
        if(!$token || $token->revoked_at || $token->expires_at->isPast() || $token->attempts >= $token->max_attempts)return null;
        $token->forceFill(['attempts'=>$token->attempts+1,'last_used_at'=>now()])->save();
        $this->audit($token->cotizacion,'OPEN_LINK','SUCCESS'); return $token;
    }

    public function audit(B2cCotizacion $q,string $action,string $result,array $metadata=[]): void
    {
        B2cGuideRecoveryAudit::create(['cotizacion_id'=>$q->id,'actor_user_id'=>auth()->id(),'action'=>$action,'result'=>$result,
            'correlation_id'=>(string)Str::uuid(),'ip_hash'=>request()?hash('sha256',(string)request()->ip()):null,'metadata'=>$metadata]);
    }

    private function sendOnce(B2cCotizacion $q,string $email,string $token,string $kind): bool
    {
        $column=$this->mailTimestampColumn($kind);
        $claimed=B2cCotizacion::whereKey($q->id)->whereNull($column)->update([$column=>now()]);
        if($claimed===1) {
            try { Mail::to($email)->send(new GuideRecoveryMail($q->fresh(),$token,$kind)); return true; }
            catch(Throwable $e) {
                B2cCotizacion::whereKey($q->id)->update([$column=>null]);
                Log::warning('No fue posible enviar correo de recuperación',['cotizacion_id'=>$q->id,'kind'=>$kind,'exception'=>get_class($e)]);
                return false;
            }
        }
        return false;
    }

    private function mailTimestampColumn(string $kind): string
    {
        return match($kind){'processing'=>'guide_processing_email_sent_at','generated'=>'guide_generated_email_sent_at','recovered'=>'guide_pdf_recovered_email_sent_at',default=>'guide_pending_email_sent_at'};
    }
}
