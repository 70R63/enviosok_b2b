<?php
namespace App\Mail;
use App\Models\B2cCotizacion;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
final class GuideRecoveryMail extends Mailable
{
    use Queueable,SerializesModels;
    public function __construct(public B2cCotizacion $cotizacion,public string $recoveryToken,public string $kind){}
    public function build()
    {
        $root = rtrim((string) (config('zigo_domains.portals.b2c.url') ?: config('app.url')), '/');
        $recoveryUrl = $root.'/envio/recuperar/'.rawurlencode($this->recoveryToken);

        return $this->subject(match($this->kind){'processing'=>'Pago confirmado; estamos procesando tu guía','generated'=>'Tu guía está disponible','recovered'=>'Recuperamos el PDF de tu guía',default=>'Tu guía sigue en recuperación'})
            ->view('email.guide-recovery', compact('recoveryUrl'));
    }
}
