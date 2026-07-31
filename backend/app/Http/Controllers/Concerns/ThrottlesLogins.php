<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Límite de intentos de login (fuerza bruta), compartido entre el login web
 * (sesión) y el de la API (Sanctum) para no duplicar la lógica.
 */
trait ThrottlesLogins
{
    protected function maxLoginAttempts(): int
    {
        return 5;
    }

    protected function loginDecaySeconds(): int
    {
        return 60;
    }

    /**
     * Clave por email+IP: castiga los intentos contra una cuenta concreta
     * sin poder bloquear a otros usuarios que compartan IP, y sin que
     * cambiar de IP sirva para saltarse el límite en una cuenta dada.
     */
    protected function throttleKey(Request $request): string
    {
        return Str::transliterate(Str::lower((string) $request->input('email'))).'|'.$request->ip();
    }

    /**
     * @throws ValidationException si se ha superado el límite de intentos.
     */
    protected function ensureLoginIsNotThrottled(Request $request): void
    {
        $key = $this->throttleKey($request);

        if (! RateLimiter::tooManyAttempts($key, $this->maxLoginAttempts())) {
            return;
        }

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', ['seconds' => RateLimiter::availableIn($key)]),
        ])->status(429);
    }

    protected function incrementLoginAttempts(Request $request): void
    {
        RateLimiter::hit($this->throttleKey($request), $this->loginDecaySeconds());
    }

    protected function clearLoginAttempts(Request $request): void
    {
        RateLimiter::clear($this->throttleKey($request));
    }
}
