<?php

namespace App\Policies;

use App\Models\Commission;
use App\Models\User;

class CommissionPolicy
{
    public function view(User $user, Commission $commission): bool
    {
        return $user->id === $commission->user_id;
    }

    public function update(User $user, Commission $commission): bool
    {
        return $user->id === $commission->user_id;
    }

    public function delete(User $user, Commission $commission): bool
    {
        return $user->id === $commission->user_id;
    }
}
