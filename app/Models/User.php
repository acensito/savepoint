<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Sanctum\HasApiTokens;

#[Fillable([
    'name', 'email', 'password', 'is_admin', 'auto_igdb_background',
    'theme', 'games_view', 'default_sort', 'default_dir', 'default_per_page',
    'default_region', 'default_edition_id', 'quick_search_exclude_wishlist',
    'igdb_enabled', 'igdb_client_id', 'igdb_client_secret',
    'hide_for_sale_from_collection',
])]
#[Hidden(['password', 'remember_token', 'igdb_client_secret'])]
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
}