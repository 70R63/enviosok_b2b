<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ZigoResetPasswordNotification extends Notification
{
    use Queueable;

    public function __construct(
        private string $token
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $email = $notifiable->getEmailForPasswordReset();

        $url = route('password.reset', [
            'token' => $this->token,
            'email' => $email,
        ]);

        $expireMinutes = (int) config(
            'auth.passwords.users.expire',
            60
        );

        return (new MailMessage)
            ->subject('Restablece tu contraseña de ZIGO')
            ->greeting('Hola')
            ->line(
                'Recibimos una solicitud para restablecer '
                . 'la contraseña de tu cuenta ZIGO.'
            )
            ->action('Restablecer contraseña', $url)
            ->line(
                "Este enlace es válido durante "
                . "{$expireMinutes} minutos."
            )
            ->line(
                'Si no solicitaste este cambio, puedes '
                . 'ignorar este mensaje. Tu contraseña '
                . 'permanecerá sin cambios.'
            )
            ->salutation('Equipo de Seguridad ZIGO');
    }

    public function toArray(object $notifiable): array
    {
        return [];
    }
}