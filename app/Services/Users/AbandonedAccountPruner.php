<?php

namespace App\Services\Users;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Purga cuentas que se registraron con 2FA activo (siempre, ver
 * RegisterController::register()) y nunca llegaron a completar el primer
 * desafío — huérfanas de verdad, no solo "con un código pendiente ahora
 * mismo" (ver User::hasAbandonedTwoFactorChallenge, issue #10). No hace
 * falta ningún margen para que el dueño legítimo recupere el acceso antes de
 * purgarse: sigue pudiendo ir a /login en cualquier momento (ya tiene
 * contraseña válida), que le reenvía el código igual que "Reenviar código" —
 * el plazo de gracia es solo para no purgar en caliente un registro que
 * todavía está a medias.
 *
 * Sin job programado a propósito: un botón manual en el panel
 * (UserController::pruneAbandoned()) es todo lo que hace falta para un
 * puñado de cuentas ocasionales — automatizarlo puede añadirse después si
 * hace falta de verdad.
 */
class AbandonedAccountPruner
{
    public const GRACE_PERIOD_DAYS = 7;

    /**
     * Borra las cuentas abandonadas y devuelve cuántas se han borrado.
     */
    public function prune(): int
    {
        $count = 0;

        $this->query()->each(function (User $user) use (&$count) {
            $user->delete();
            $count++;
        });

        return $count;
    }

    /**
     * Cuántas cuentas hay ahora mismo pendientes de purgar, para mostrarlo
     * en el panel sin tener que borrar nada.
     */
    public function pendingCount(): int
    {
        return $this->query()->count();
    }

    /**
     * @return Builder<User>
     */
    private function query(): Builder
    {
        // doesntHave('games'): defensa adicional, no debería hacer falta —
        // two_factor_verified_at null implica que la cuenta nunca completó
        // un login real, y games.user_id exige sesión autenticada para
        // crearse. Mismo criterio que ya bloquea el borrado manual en
        // UserController::destroy(): no borrar nunca una cuenta con
        // colección, ni siquiera "en teoría imposible" de tenerla.
        return User::where('two_factor_enabled', true)
            ->whereNull('two_factor_verified_at')
            ->where('created_at', '<=', now()->subDays(self::GRACE_PERIOD_DAYS))
            ->doesntHave('games');
    }
}
