<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Edition extends Model
{
    protected $fillable = ['name'];

    /**
     * Una edición puede estar disponible en múltiples plataformas.
     */
    public function platforms(): BelongsToMany
    {
        return $this->belongsToMany(Platform::class);
    }

    /**
     * Una edición puede tener muchos juegos registrados.
     */
    public function games(): HasMany
    {
        return $this->hasMany(Game::class);
    }
}