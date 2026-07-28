<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Platform extends Model
{
    protected $fillable = [
        'name', 'slug', 'label', 'manufacturer_id',
        'bg_color', 'text_color', 'border_color',
    ];

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

    // ==========================================
    // CHIP: etiqueta y colores (con fallback al fabricante)
    // ==========================================

    public function chipLabel(): string
    {
        return $this->label ?: $this->name;
    }

    public function effectiveBgColor(): string
    {
        return $this->bg_color ?? $this->manufacturer?->bg_color ?? '#EEF2FF';
    }

    public function effectiveTextColor(): string
    {
        return $this->text_color ?? $this->manufacturer?->text_color ?? '#4338CA';
    }

    public function effectiveBorderColor(): string
    {
        return $this->border_color ?? $this->manufacturer?->border_color ?? '#C7D2FE';
    }
}