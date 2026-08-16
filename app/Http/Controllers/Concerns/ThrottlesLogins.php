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
     * Límite adicional solo por email, más laxo que el de email+IP: no
     * castiga el uso normal desde varios dispositivos, pero pone un tope
     * global a una cuenta concreta aunque el atacante rote de IP en cada
     * intento (lo que resetearía el límite por email+IP de abajo cada vez).
     */
    protected function maxLoginAttemptsPerEmail(): int
    {
        return 10;
    }

    protected function loginDecaySecondsPerEmail(): int
    {
        return 300;
    }

    /**
     * Clave por email+IP: castiga los intentos contra una cuenta concreta
     * sin poder bloquear a otros usuarios que compartan IP, y sin que
     * cambiar de IP sirva para saltarse el límite en una cuenta dada.
     */
    protected function throttleKey(Request $request): string
    {
        return $this->normalizedEmail($request).'|'.$request->ip();
    }

    protected function throttleKeyByEmail(Request $request): string
    {
        return 'email:'.$this->normalizedEmail($request);
    }

    protected function normalizedEmail(Request $request): string
    {
        return Str::transliterate(Str::lower((string) $request->input('email')));
    }

    /**
     * @throws ValidationException si se ha superado alguno de los dos límites.
     */
    protected function ensureLoginIsNotThrottled(Request $request): void
    {
        $key = $this->throttleKey($request);
        $emailKey = $this->throttleKeyByEmail($request);

        $throttledKey = match (true) {
            RateLimiter::tooManyAttempts($key, $this->maxLoginAttempts()) => $key,
            RateLimiter::tooManyAttempts($emailKey, $this->maxLoginAttemptsPerEmail()) => $emailKey,
            default => null,
        };

        if ($throttledKey === null) {
            return;
        }

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', ['seconds' => RateLimiter::availableIn($throttledKey)]),
        ])->status(429);
    }

    protected function incrementLoginAttempts(Request $request): void
    {
        RateLimiter::hit($this->throttleKey($request), $this->loginDecaySeconds());
        RateLimiter::hit($this->throttleKeyByEmail($request), $this->loginDecaySecondsPerEmail());
    }

    protected function clearLoginAttempts(Request $request): void
    {
        RateLimiter::clear($this->throttleKey($request));
        RateLimiter::clear($this->throttleKeyByEmail($request));
    }
}
