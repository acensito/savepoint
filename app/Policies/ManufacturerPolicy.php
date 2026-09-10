<?php

namespace App\Policies;

use App\Models\Manufacturer;
use App\Models\User;

/**
 * Catálogo por cuenta (issue #175): mismo patrón que GamePolicy, sin
 * restore/forceDelete porque Manufacturer no lleva soft delete.
 */
class ManufacturerPolicy
{
    public function view(User $user, Manufacturer $manufacturer): bool
    {
        return $user->id === $manufacturer->user_id;
    }

    public function update(User $user, Manufacturer $manufacturer): bool
    {
        return $user->id === $manufacturer->user_id;
    }

    public function delete(User $user, Manufacturer $manufacturer): bool
    {
        return $user->id === $manufacturer->user_id;
    }
}
