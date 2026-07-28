<?php

namespace App\Policies;

use App\Models\Game;
use App\Models\User;

class GamePolicy
{
    /**
     * Un usuario solo puede ver los juegos de su propia colección.
     */
    public function view(User $user, Game $game): bool
    {
        return $user->id === $game->user_id;
    }

    /**
     * Un usuario solo puede editar los juegos de su propia colección.
     */
    public function update(User $user, Game $game): bool
    {
        return $user->id === $game->user_id;
    }

    /**
     * Un usuario solo puede enviar a la papelera sus propios juegos.
     */
    public function delete(User $user, Game $game): bool
    {
        return $user->id === $game->user_id;
    }

    /**
     * Para cuando implementes la papelera de reciclaje en la interfaz.
     */
    public function restore(User $user, Game $game): bool
    {
        return $user->id === $game->user_id;
    }

    /**
     * Borrado definitivo (saltándose el soft delete).
     */
    public function forceDelete(User $user, Game $game): bool
    {
        return $user->id === $game->user_id;
    }
}