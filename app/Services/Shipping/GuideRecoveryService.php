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
        $plain=Str::random(80); $normalized=Str::lower(trim($email));
        DB::transaction(function()use($q,$normalized,$plain){
            B2cGuideRecoveryToken::where('cotizacion_id',$q->id)->whereNull('revoked_at')->update(['revoked_at'=>now()]);
            B2cGuideRecoveryToken::create(['cotizacion_id'=>$q->id,'email_hash'=>hash('sha256',$normalized),'token_hash'=>hash('sha256',$plain),'expires_at'=>now()->addHours((int)config('zigo_guide_recovery.token_hours',48)),'max_attempts'=>(int)config('zigo_guide_recovery.max_attempts',10)]);
        });
        $this->sendOnce($q,$normalized,$plain,$mailKind);
        $this->audit($q,'ISSUE_LINK','SUCCESS');
        return $plain;
    }

    public function resolve(string $plain): ?B2cGuideRecoveryToken
    {
        $token=B2cGuideRecoveryToken::where('token_hash',hash('sha256',$plain))->first();
        if(!$token || $token->revoked_at || $token->expires_at->isPast() || $token->attempts >= $token->max_attempts)return null;
        $token->forceFill(['attempts'=>$token->attempts+1,'last_used_at'=>now()])->save();
        $this->audit($token->cotizacion,'OPEN_LINK','SUCCESS'); return $token;
    }

    public function audit(B2cCotizacion $q,string $action,string $result,array $metadata=[]): void
    {
        B2cGuideRecoveryAudit::create(['cotizacion_id'=>$q->id,'actor_user_id'=>auth()->id(),'action'=>$action,'result'=>$result,
            'correlation_id'=>(string)Str::uuid(),'ip_hash'=>request()?hash('sha256',(string)request()->ip()):null,'metadata'=>$metadata]);
    }

    private function sendOnce(B2cCotizacion $q,string $email,string $token,string $kind): void
    {
        $column=match($kind){'processing'=>'guide_processing_email_sent_at','generated'=>'guide_generated_email_sent_at','recovered'=>'guide_pdf_recovered_email_sent_at',default=>'guide_pending_email_sent_at'};
        $claimed=B2cCotizacion::whereKey($q->id)->whereNull($column)->update([$column=>now()]);
        if($claimed===1) {
            try { Mail::to($email)->send(new GuideRecoveryMail($q->fresh(),$token,$kind)); }
            catch(Throwable $e) {
                B2cCotizacion::whereKey($q->id)->update([$column=>null]);
                Log::warning('No fue posible enviar correo de recuperación',['cotizacion_id'=>$q->id,'kind'=>$kind,'exception'=>get_class($e)]);
            }
        }
    }
}
