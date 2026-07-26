<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Platform extends Model
{
    protected $fillable = ['name', 'slug', 'manufacturer_id'];

    /**
     * Una plataforma pertenece a un fabricante.
     */
    public function manufacturer(): BelongsTo
    {
        return $this->belongsTo(Manufacturer::class);
    }

    /**
     * Una plataforma tiene muchos juegos.
     */
    public function games(): HasMany
    {
        return $this->hasMany(Game::class);
    }

    /**
     * Una plataforma puede estar en múltiples ediciones.
     */
    public function editions(): BelongsToMany
    {
        return $this->belongsToMany(Edition::class);
    }
}