<?php

namespace App\Policies;

use App\Models\Platform;
use App\Models\User;

/**
 * Catálogo por cuenta (issue #175): mismo patrón que GamePolicy, sin
 * restore/forceDelete porque Platform no lleva soft delete.
 */
class PlatformPolicy
{
    public function view(User $user, Platform $platform): bool
    {
        return $user->id === $platform->user_id;
    }

    public function update(User $user, Platform $platform): bool
    {
        return $user->id === $platform->user_id;
    }

    public function delete(User $user, Platform $platform): bool
    {
        return $user->id === $platform->user_id;
    }
}
