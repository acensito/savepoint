<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Edition extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'format'];

    public const FORMAT_PHYSICAL = 'physical';
    public const FORMAT_DIGITAL = 'digital';
    public const FORMAT_CIAB = 'ciab';

    public const FORMATS = [
        self::FORMAT_PHYSICAL => ['label' => 'Físico', 'icon' => 'album'],
        self::FORMAT_DIGITAL => ['label' => 'Digital', 'icon' => 'cloud'],
        self::FORMAT_CIAB => ['label' => 'CIAB (caja completa)', 'icon' => 'inventory_2'],
    ];

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

    // ==========================================
    // FORMATO: etiqueta e ícono (físico/digital/CIAB)
    // ==========================================

    public function formatLabel(): string
    {
        return self::FORMATS[$this->format]['label'] ?? self::FORMATS[self::FORMAT_PHYSICAL]['label'];
    }

    public function formatIcon(): string
    {
        return self::FORMATS[$this->format]['icon'] ?? self::FORMATS[self::FORMAT_PHYSICAL]['icon'];
    }
}