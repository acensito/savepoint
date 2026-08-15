<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Solo un admin puede listar/gestionar las cuentas de la plataforma.
     */
    public function viewAny(User $authUser): bool
    {
        return $authUser->is_admin;
    }

    public function create(User $authUser): bool
    {
        return $authUser->is_admin;
    }

    public function update(User $authUser, User $target): bool
    {
        return $authUser->is_admin;
    }

    /**
     * Un admin puede borrar cualquier cuenta salvo la suya propia (evita
     * quedarse sin acceso al panel de gestión sin querer).
     */
    public function delete(User $authUser, User $target): bool
    {
        return $authUser->is_admin && $authUser->id !== $target->id;
    }
}
