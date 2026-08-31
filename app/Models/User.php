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
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property Carbon|null $two_factor_code_expires_at
 */
#[Fillable([
    'name', 'email', 'password', 'is_admin', 'auto_igdb_background',
    'theme', 'games_view', 'navbar_color', 'default_sort', 'default_dir', 'default_per_page',
    'default_region', 'default_edition_id', 'quick_search_exclude_wishlist',
    'igdb_enabled', 'igdb_client_id', 'igdb_client_secret',
    'hide_for_sale_from_collection', 'avatar_path', 'two_factor_enabled',
    'section_wishlist_enabled', 'section_commissions_enabled', 'section_for_sale_enabled',
    'section_sales_enabled', 'section_stats_enabled',
    'created_at',
])]
#[Hidden(['password', 'remember_token', 'igdb_client_secret', 'two_factor_code'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Eloquent::create() no relee los defaults que pone la propia columna en
     * la base de datos para los atributos omitidos: el modelo recién creado
     * se queda con null en memoria (que el cast 'boolean' vuelve false) hasta
     * el próximo fresh()/refresh(). Sin repetirlo aquí, estas 5 secciones
     * (todas con default true en la migración, ver issue #32) arrancarían
     * "desactivadas" para cualquier código que use el mismo objeto en
     * memoria justo tras crearlo (p. ej. actingAs() en un test).
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'section_wishlist_enabled' => true,
        'section_commissions_enabled' => true,
        'section_for_sale_enabled' => true,
        'section_sales_enabled' => true,
        'section_stats_enabled' => true,
    ];

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
            'section_wishlist_enabled' => 'boolean',
            'section_commissions_enabled' => 'boolean',
            'section_for_sale_enabled' => 'boolean',
            'section_sales_enabled' => 'boolean',
            'section_stats_enabled' => 'boolean',
        ];
    }

    // ==========================================
    // VALIDACIÓN
    // ==========================================

    /**
     * Única fuente de verdad para la complejidad de contraseña, exigida en
     * todos los sitios que crean o cambian una (RegisterController,
     * UserController::store()/update(), ProfileController::updatePassword(),
     * PasswordResetController) — antes solo la aplicaba el registro público,
     * así que un admin dando de alta un usuario a mano, un reseteo por
     * email o un cambio de contraseña propio/ajeno se conformaban con
     * min:8, sin exigir nada de complejidad (ver issue #51).
     */
    public static function passwordComplexityRule(): Password
    {
        return Password::min(8)->letters()->numbers()->symbols()->mixedCase();
    }

    // ==========================================
    // RELACIONES
    // ==========================================

    /**
     * Un usuario tiene muchos juegos en su colección.
     *
     * @return HasMany<Game, $this>
     */
    public function games(): HasMany
    {
        return $this->hasMany(Game::class);
    }

    /**
     * @return HasMany<TwoFactorTrustedDevice, $this>
     */
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
