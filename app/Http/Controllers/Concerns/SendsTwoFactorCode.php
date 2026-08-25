<?php

namespace App\Http\Controllers\Concerns;

use App\Models\User;
use App\Notifications\TwoFactorCodeNotification;
use Throwable;

/**
 * Generar y mandar el código de 2FA es el mismo paso en tres sitios (login
 * web, reenvío web, login API): genera uno nuevo (invalida el anterior, ver
 * User::generateTwoFactorCode) y lo notifica, capturando cualquier fallo de
 * envío para que nunca tumbe la petición con un 500 sin manejar. Cada
 * llamador decide qué hacer si falla (redirect vs JSON), aquí solo el envío.
 */
trait SendsTwoFactorCode
{
    protected function sendTwoFactorCode(User $user): bool
    {
        try {
            $user->notify(new TwoFactorCodeNotification($user->generateTwoFactorCode()));

            return true;
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }
}
