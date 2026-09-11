<?php

namespace App\Models;

use Database\Factories\ManufacturerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Manufacturer extends Model
{
    /** @use HasFactory<ManufacturerFactory> */
    use HasFactory;

    protected $fillable = ['user_id', 'name', 'slug', 'bg_color', 'text_color', 'border_color'];

    /**
     * Catálogo por cuenta (issue #175): cada usuario gestiona sus propios
     * fabricantes, sin dato compartido con el resto.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Un fabricante tiene múltiples plataformas.
     *
     * @return HasMany<Platform, $this>
     */
    public function platforms(): HasMany
    {
        return $this->hasMany(Platform::class);
    }
}
