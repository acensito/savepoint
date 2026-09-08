<?php

namespace App\Http\Controllers\Concerns;

use App\Models\User;

trait AppliesThemePreference
{
    protected function applyPendingTheme(User $user, ?string $theme): void
    {
        if (in_array($theme, User::THEMES, true)) {
            $user->forceFill(['theme' => $theme])->save();
        }
    }
}
