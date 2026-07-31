<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Manufacturer extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'bg_color', 'text_color', 'border_color'];

    /**
     * Un fabricante tiene múltiples plataformas.
     */
    public function platforms(): HasMany
    {
        return $this->hasMany(Platform::class);
    }
}