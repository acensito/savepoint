<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Manufacturer extends Model
{
    protected $fillable = ['name', 'slug'];

    /**
     * Un fabricante tiene múltiples plataformas.
     */
    public function platforms(): HasMany
    {
        return $this->hasMany(Platform::class);
    }
}