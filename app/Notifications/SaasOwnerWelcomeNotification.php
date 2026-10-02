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
            $mail->line('Para comenzar, activa tu cuenta y establece una contraseña. Al finalizar te guiaremos automáticamente por la configuración inicial de tu plataforma.')
                ->action('ACTIVAR Y CONFIGURAR MI CUENTA', $this->actionUrl)
                ->line('Este enlace es válido durante '.self::formatDuration($this->expiresInMinutes).' y sólo puede utilizarse una vez.');
        } else {
            $mail->line('Puedes ingresar con tu cuenta ZIGO existente.')
                ->action('IR A MI PLATAFORMA', $this->actionUrl)
                ->line('¿No recuerdas tu contraseña? Restablece tu acceso desde el login: '.rtrim($this->adminUrl, '/').'/forgot-password');
        }

        return $mail
            ->salutation('Equipo ZIGO');
    }

    public static function formatDuration(int $minutes): string
    {
        if ($minutes > 0 && $minutes % 60 === 0) {
            $hours = intdiv($minutes, 60);
            return $hours.' '.($hours === 1 ? 'hora' : 'horas');
        }

        return $minutes.' '.($minutes === 1 ? 'minuto' : 'minutos');
    }
}
