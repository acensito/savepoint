<?php

namespace App\Models;

use Database\Factories\EditionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Edition extends Model
{
    /** @use HasFactory<EditionFactory> */
    use HasFactory;

    protected $fillable = ['user_id', 'name', 'format'];

    /**
     * La columna `format` de la tabla sigue teniendo 'physical' como default
     * a nivel de esquema (valor que ya no es válido, ver migración #142) —
     * cambiarlo requeriría doctrine/dbal, que no está instalado. En vez de
     * eso, se cubre aquí a nivel de modelo: GameCsvImporter::resolveEdition()
     * (y cualquier otro Edition::create() sin 'format' explícito) obtiene
     * siempre un valor válido, sin depender de qué tenga la columna.
     */
    protected $attributes = [
        'format' => self::FORMAT_PHYSICAL_DISC,
    ];

    public const FORMAT_PHYSICAL_CARTRIDGE = 'physical_cartridge';

    public const FORMAT_PHYSICAL_DISC = 'physical_disc';

    public const FORMAT_PHYSICAL_FLOPPY = 'physical_floppy';

    public const FORMAT_PHYSICAL_TAPE = 'physical_tape';

    public const FORMAT_PHYSICAL_OTHER = 'physical_other';

    public const FORMAT_DIGITAL = 'digital';

    public const FORMAT_CIAB = 'ciab';

    /**
     * Físico desglosado por soporte concreto (#142): antes "físico" era una
     * única categoría, insuficiente para controlar colecciones de PC/retro
     * donde el mismo tipo de plataforma puede tener soportes muy distintos
     * (cartucho, disco, diskette, cinta...). Digital y CIAB se quedan igual
     * que antes — CIAB ("Code In A Box") es un código de descarga que viene
     * en caja física, una categoría híbrida a propósito, no un subtipo de
     * físico ni de digital.
     */
    public const FORMATS = [
        self::FORMAT_PHYSICAL_CARTRIDGE => ['label' => 'Físico (cartucho)', 'icon' => 'sd_card'],
        self::FORMAT_PHYSICAL_DISC => ['label' => 'Físico (disco)', 'icon' => 'album'],
        self::FORMAT_PHYSICAL_FLOPPY => ['label' => 'Físico (diskette)', 'icon' => 'save'],
        // 'radio' es una aproximación: Material Symbols no tiene ningún
        // icono de cassette/cinta (verificado contra el listado oficial de
        // google/material-design-icons) — ver #149 para sustituirlo por un
        // SVG propio en cuanto exista soporte para iconos personalizados.
        self::FORMAT_PHYSICAL_TAPE => ['label' => 'Físico (cassette)', 'icon' => 'radio'],
        self::FORMAT_PHYSICAL_OTHER => ['label' => 'Físico (otros)', 'icon' => 'usb'],
        self::FORMAT_DIGITAL => ['label' => 'Digital', 'icon' => 'cloud'],
        self::FORMAT_CIAB => ['label' => 'CIAB (código en caja)', 'icon' => 'card_giftcard'],
    ];

    /**
     * Catálogo por cuenta (issue #175): cada usuario gestiona sus propias
     * ediciones, sin dato compartido con el resto.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Una edición puede estar disponible en múltiples plataformas.
     *
     * @return BelongsToMany<Platform, $this>
     */
    public function platforms(): BelongsToMany
    {
        return $this->belongsToMany(Platform::class);
    }

    /**
     * Una edición puede tener muchos juegos registrados.
     *
     * @return HasMany<Game, $this>
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
        return self::FORMATS[$this->format]['label'] ?? self::FORMATS[self::FORMAT_PHYSICAL_DISC]['label'];
    }

    public function formatIcon(): string
    {
        return self::FORMATS[$this->format]['icon'] ?? self::FORMATS[self::FORMAT_PHYSICAL_DISC]['icon'];
    }
}
