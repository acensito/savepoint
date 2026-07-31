<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Game extends Model
{
    // Activamos la papelera de reciclaje para no perder datos por error
    use HasFactory, SoftDeletes;

    /**
     * Los atributos que se pueden asignar de forma masiva.
     */
    protected $fillable = [
        'user_id',
        'ean',
        'title',
        'platform_id',
        'cover',
        'data',
        'release_date',
        'developer',
        'genres',
        'status',
        'play_status',
        'condition',
        'edition_id',
        'notes',
        'rating',
        'price_paid',
        'purchase_place',
        'purchase_date',
        'manual_status',
        'region',
        'age_rating',
    ];

    /**
     * Conversión automática de tipos de datos.
     */
    protected function casts(): array
    {
        return [
            'release_date' => 'date',
            'purchase_date' => 'date',
            'genres' => 'array', // Laravel convierte el JSON de Postgres a un array de PHP automáticamente
            'price_paid' => 'decimal:2',
            'rating' => 'integer',
        ];
    }

    // ==========================================
    // RELACIONES
    // ==========================================

    /**
     * Un juego pertenece a un único usuario.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Un juego pertenece a una plataforma.
     */
    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class);
    }

    /**
     * Un juego pertenece a una edición específica (Opcional).
     */
    public function edition(): BelongsTo
    {
        return $this->belongsTo(Edition::class);
    }

    // ==========================================
    // CARÁTULA
    // ==========================================

    /**
     * URL pública de la carátula, o null si no tiene.
     *
     * Usamos el helper url() (consciente de la petición actual) en vez de
     * Storage::disk('public')->url(), que depende de APP_URL y puede acabar
     * generando enlaces a "localhost" cuando se accede por IP/dominio.
     */
    public function coverUrl(): ?string
    {
        return $this->cover ? url('storage/' . $this->cover) : null;
    }

    /**
     * Iniciales (hasta 2 letras) para el recuadro que sustituye a la carátula
     * cuando el juego no tiene una subida.
     */
    public function coverInitials(): string
    {
        $words = preg_split('/\s+/', trim($this->title)) ?: [];

        $initials = collect($words)
            ->take(2)
            ->map(fn (string $word) => mb_strtoupper(mb_substr($word, 0, 1)))
            ->implode('');

        return $initials !== '' ? $initials : '?';
    }
}