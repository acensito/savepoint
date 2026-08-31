<?php

namespace App\Models;

use Database\Factories\ManufacturerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Manufacturer extends Model
{
    /** @use HasFactory<ManufacturerFactory> */
    use HasFactory;

    protected $fillable = ['name', 'slug', 'bg_color', 'text_color', 'border_color'];

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
