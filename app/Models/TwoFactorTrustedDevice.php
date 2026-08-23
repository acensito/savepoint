<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable(['user_id', 'token_hash', 'expires_at', 'ip_address', 'user_agent'])]
class TwoFactorTrustedDevice extends Model
{
    /**
     * Nombre de la cookie compartido entre AuthController (para leerla) y
     * TwoFactorController (para emitirla).
     */
    public const COOKIE_NAME = 'sp_trusted_device';

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Crea un dispositivo de confianza nuevo y devuelve el token en claro
     * (para la cookie); solo su hash queda en BD.
     *
     * sha256 (no Hash::make/bcrypt) a propósito: necesita ser determinista
     * para poder buscarlo por igualdad en isTrusted(), igual que hace
     * Sanctum con sus tokens de acceso personal.
     */
    public static function issueFor(User $user, ?string $ipAddress, ?string $userAgent): string
    {
        $token = Str::random(60);

        $user->twoFactorTrustedDevices()->create([
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addDays(30),
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);

        return $token;
    }

    /**
     * Comprueba si el token de la cookie corresponde a un dispositivo de
     * confianza vigente para este usuario.
     */
    public static function isTrusted(User $user, ?string $token): bool
    {
        if (! $token) {
            return false;
        }

        return $user->twoFactorTrustedDevices()
            ->where('token_hash', hash('sha256', $token))
            ->where('expires_at', '>', now())
            ->exists();
    }
}
