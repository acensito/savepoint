<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Primera notificación real de la app (hasta ahora solo se usaba el
 * ResetPassword interno de Laravel para la recuperación de contraseña). Sin
 * ShouldQueue a propósito: el usuario está esperando el código en el
 * momento, se manda síncrono.
 */
class TwoFactorCodeNotification extends Notification
{
    public function __construct(public readonly string $code) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Tu código de verificación de Savepoint')
            ->greeting('Tu código es: '.$this->code)
            ->line('Pégalo en la pantalla de verificación y en un segundo estás dentro de tu colección.')
            ->line('Caduca en **10 minutos**, así que no te entretengas mucho.')
            ->line('¿No has sido tú? Tranquilo, no ha pasado nada: puedes ignorar este email.')
            // salutation() explícito, no el "Regards," por defecto de
            // Laravel: esa cadena no tiene traducción cargada en lang/es/,
            // y así queda igual de a mano que el resto del texto del email.
            ->salutation('¡Gracias por usar Savepoint! 🎮');
    }
}
