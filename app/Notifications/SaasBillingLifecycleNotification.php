<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class SaasBillingLifecycleNotification extends Notification
{
    use Queueable;

    public function __construct(private string $type, private array $data, private ?string $paymentUrl = null) {}
    public function via($notifiable): array { return ['mail']; }

    public function toMail($notifiable): MailMessage
    {
        $titles=['SUBSCRIPTION_EXPIRING'=>'Tu suscripción ZIGO está próxima a vencer','SUBSCRIPTION_EXPIRES_TODAY'=>'Tu suscripción ZIGO vence hoy','SUBSCRIPTION_EXPIRED'=>'Tu suscripción ZIGO ha vencido','SUSPENSION_WARNING'=>'Aviso de suspensión de ZIGO Platform','SUBSCRIPTION_SUSPENDED'=>'Tu suscripción ZIGO fue suspendida','SUBSCRIPTION_REACTIVATED'=>'Tu suscripción ZIGO está activa nuevamente','RENEWAL_PAYMENT_CONFIRMED'=>'Pago de renovación confirmado'];
        $expired=in_array($this->type,['SUBSCRIPTION_EXPIRED','SUSPENSION_WARNING','SUBSCRIPTION_SUSPENDED'],true);
        $mail=(new MailMessage)->subject($titles[$this->type]??'Actualización de tu suscripción ZIGO')->greeting('Hola')->line($this->data['company'])->line('Plan: '.$this->data['plan'].' · '.$this->data['billing_period'])->line('Fecha de vencimiento: '.$this->data['expiration']);
        if($this->type==='SUBSCRIPTION_EXPIRING')$mail->line('Próxima renovación en '.$this->data['days_remaining'].' días.');
        if($this->type==='SUBSCRIPTION_EXPIRES_TODAY')$mail->line('Tu vigencia vence hoy.');
        if($expired)$mail->line($this->type==='SUBSCRIPTION_SUSPENDED'?'El servicio se encuentra suspendido hasta confirmar el pago.':'La vigencia se encuentra vencida.');
        if($this->data['total']!==null){$label=$expired?'Saldo pendiente':($this->type==='RENEWAL_PAYMENT_CONFIRMED'?'Importe confirmado':'Importe de renovación');$mail->line($label.': '.$this->money($this->data['total']).' '.$this->data['currency']);if($this->data['subtotal']!==null)$mail->line('Subtotal: '.$this->money($this->data['subtotal']).' '.$this->data['currency']);if($this->data['tax_amount']!==null)$mail->line('Impuestos: '.$this->money($this->data['tax_amount']).' '.$this->data['currency']);}
        if($this->type==='RENEWAL_PAYMENT_CONFIRMED')$mail->line('Nueva vigencia: '.$this->data['new_period_end'])->line('Estado: ACTIVE');
        if($this->paymentUrl)$mail->action($expired?'Realizar pago':'Renovar ahora',$this->paymentUrl);
        return$mail->line('Nunca enviaremos contraseñas ni credenciales por correo.');
    }

    private function money(string|int|float $amount): string { return '$'.number_format((float)$amount,2,'.',','); }
}
