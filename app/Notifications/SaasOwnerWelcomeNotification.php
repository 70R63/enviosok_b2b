<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class SaasOwnerWelcomeNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $company,
        private readonly string $adminUrl,
        private readonly string $actionUrl,
        private readonly bool $requiresActivation,
        private readonly int $expiresInMinutes,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('Tu plataforma ZIGO está lista')
            ->greeting('Hola '.$notifiable->name)
            ->line('Tu plataforma ZIGO para '.$this->company.' ya está lista.')
            ->line('URL administrativa: '.$this->adminUrl);

        if ($this->requiresActivation) {
            $mail->line('Establece una contraseña para activar tu acceso administrativo.')
                ->action('ACTIVAR MI CUENTA', $this->actionUrl)
                ->line('Este enlace es válido durante '.$this->expiresInMinutes.' minutos y sólo puede utilizarse una vez.');
        } else {
            $mail->line('Puedes ingresar con tu cuenta ZIGO existente.')
                ->action('IR A MI PLATAFORMA', $this->actionUrl);
        }

        return $mail->line('Si necesitas recuperar tu contraseña, utiliza la opción disponible en el login.')
            ->salutation('Equipo ZIGO');
    }
}
