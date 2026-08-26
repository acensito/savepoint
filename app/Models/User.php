<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\HasApiTokens;

#[Fillable([
    'name', 'email', 'password', 'is_admin', 'auto_igdb_background',
    'theme', 'games_view', 'navbar_color', 'default_sort', 'default_dir', 'default_per_page',
    'default_region', 'default_edition_id', 'quick_search_exclude_wishlist',
    'igdb_enabled', 'igdb_client_id', 'igdb_client_secret',
    'hide_for_sale_from_collection', 'avatar_path', 'two_factor_enabled',
    'created_at',
])]
#[Hidden(['password', 'remember_token', 'igdb_client_secret', 'two_factor_code'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'auto_igdb_background' => 'boolean',
            'default_per_page' => 'integer',
            'default_edition_id' => 'integer',
            'quick_search_exclude_wishlist' => 'boolean',
            'hide_for_sale_from_collection' => 'boolean',
            'igdb_enabled' => 'boolean',
            // Cifrado en reposo (APP_KEY): es la única credencial secreta
            // que guarda esta tabla, a diferencia de igdb_client_id.
            'igdb_client_secret' => 'encrypted',
            'two_factor_enabled' => 'boolean',
            'two_factor_code_expires_at' => 'datetime',
        ];
    }

    // ==========================================
    // RELACIONES
    // ==========================================

    /**
     * Un usuario tiene muchos juegos en su colección.
     */
    public function games(): HasMany
    {
        return $this->hasMany(Game::class);
    }

    public function twoFactorTrustedDevices(): HasMany
    {
        return $this->hasMany(TwoFactorTrustedDevice::class);
    }

    // ==========================================
    // ACCESSORS
    // ==========================================

    /**
     * Devuelve la URL pública del avatar o null si el usuario no tiene ninguno.
     * Centraliza la resolución para no repetir asset() en las vistas.
     */
    public function avatarUrl(): ?string
    {
        return $this->avatar_path
            ? asset('storage/'.$this->avatar_path)
            : null;
    }

    // ==========================================
    // 2FA POR EMAIL
    // ==========================================

    /**
     * Genera un código de 6 dígitos, lo guarda hasheado (10 min de validez)
     * y devuelve el valor en claro para mandarlo por email — nunca se
     * persiste sin hashear.
     */
    public function generateTwoFactorCode(): string
    {
        $code = (string) random_int(100000, 999999);

        $this->forceFill([
            'two_factor_code' => Hash::make($code),
            'two_factor_code_expires_at' => now()->addMinutes(10),
        ])->save();

        return $code;
    }

    /**
     * Comprueba el código contra el hash guardado y su caducidad. Si es
     * válido, lo consume (no se puede volver a usar).
     */
    public function verifyTwoFactorCode(string $code): bool
    {
        $valid = $this->two_factor_code
            && $this->two_factor_code_expires_at?->isFuture()
            && Hash::check($code, $this->two_factor_code);

        if ($valid) {
            $this->forceFill([
                'two_factor_code' => null,
                'two_factor_code_expires_at' => null,
            ])->save();
        }

        return $valid;
    }
}
