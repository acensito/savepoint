<?php

namespace App\Models;

use Database\Factories\PlatformFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Platform extends Model
{
    /** @use HasFactory<PlatformFactory> */
    use HasFactory;

    protected $fillable = [
        'name', 'slug', 'label', 'manufacturer_id',
        'bg_color', 'text_color', 'border_color',
    ];

    /**
     * Una plataforma pertenece a un fabricante.
     *
     * @return BelongsTo<Manufacturer, $this>
     */
    public function manufacturer(): BelongsTo
    {
        return $this->belongsTo(Manufacturer::class);
    }

    /**
     * Una plataforma tiene muchos juegos.
     *
     * @return HasMany<Game, $this>
     */
    public function games(): HasMany
    {
        return $this->hasMany(Game::class);
    }

    /**
     * Una plataforma puede estar en múltiples ediciones.
     *
     * @return BelongsToMany<Edition, $this>
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

    /**
     * PHPStan marca el "?->" de abajo (y el de effectiveTextColor/
     * effectiveBorderColor) como innecesario porque, en valor, acceder a una
     * propiedad de null sin "?->" también resuelve a null y "??" lo captura
     * igual — pero sin "?->", PHP emite un warning ("Attempt to read
     * property on null") en cada plataforma sin fabricante. Se mantiene el
     * "?->" a propósito para no meter ruido en los logs; el aviso es un
     * falso positivo de PHPStan sobre el tipo, no sobre el comportamiento en
     * producción.
     */
    public function effectiveBgColor(): string
    {
        // @phpstan-ignore nullsafe.neverNull
        return $this->bg_color ?? $this->manufacturer?->bg_color ?? '#EEF2FF';
    }

    public function effectiveTextColor(): string
    {
        // @phpstan-ignore nullsafe.neverNull
        return $this->text_color ?? $this->manufacturer?->text_color ?? '#4338CA';
    }

    public function effectiveBorderColor(): string
    {
        // @phpstan-ignore nullsafe.neverNull
        return $this->border_color ?? $this->manufacturer?->border_color ?? '#C7D2FE';
    }
}
