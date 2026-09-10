<?php

namespace App\Policies;

use App\Models\Edition;
use App\Models\User;

/**
 * Catálogo por cuenta (issue #175): mismo patrón que GamePolicy, sin
 * restore/forceDelete porque Edition no lleva soft delete.
 */
class EditionPolicy
{
    public function view(User $user, Edition $edition): bool
    {
        return $user->id === $edition->user_id;
    }

    public function update(User $user, Edition $edition): bool
    {
        return $user->id === $edition->user_id;
    }

    public function delete(User $user, Edition $edition): bool
    {
        return $user->id === $edition->user_id;
    }
}
