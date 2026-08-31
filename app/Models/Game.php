<?php

namespace App\Models;

use Database\Factories\GameFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Larastan no infiere los tipos de columnas casteadas vía el método
 * casts() (a diferencia de la propiedad estática $casts): sin las
 * anotaciones de abajo, cualquier ->format() sobre estas fechas se
 * marcaba como "Cannot call method format() on string" en varios
 * controladores, aunque en tiempo de ejecución siempre son Carbon (el
 * cast 'date' así lo garantiza).
 *
 * @property Carbon|null $release_date
 * @property Carbon|null $purchase_date
 * @property Carbon|null $sold_at
 */
class Game extends Model
{
    // Activamos la papelera de reciclaje para no perder datos por error
    /** @use HasFactory<GameFactory> */
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
        'for_sale',
        'wishlist_priority',
        'wishlist_estimated_price',
        'wishlist_store',
        'play_status',
        'condition',
        'edition_id',
        'notes',
        'rating',
        'playtime_hours',
        'price_paid',
        'sale_price',
        'sold_at',
        'purchase_place',
        'purchase_date',
        'manual_status',
        'region',
        'age_rating',
        'igdb_id',
        'igdb_genres',
        'igdb_rating',
        'igdb_time_to_beat',
        'igdb_matched_at',
        'igdb_background',
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
            'for_sale' => 'boolean',
            'sale_price' => 'decimal:2',
            'sold_at' => 'date',
            'rating' => 'integer',
            'playtime_hours' => 'decimal:1',
            'wishlist_priority' => 'integer',
            'wishlist_estimated_price' => 'decimal:2',
            'igdb_genres' => 'array',
            'igdb_rating' => 'decimal:2',
            'igdb_time_to_beat' => 'array',
            'igdb_matched_at' => 'datetime',
        ];
    }

    // ==========================================
    // VALORES VÁLIDOS
    // ==========================================
    // Única fuente de verdad para las reglas de validación de web
    // (GameController::validated()/quickUpdate(), GameBulkActionController::updatePlayStatus()) y
    // API (StoreGameRequest/UpdateGameRequest) — antes cada sitio repetía su
    // propia lista/rango a mano, y habían llegado a desincronizarse (rating
    // 1-10 en la API, 1-5 en la web; status/play_status como string libre en
    // la API, enums cerrados en la web).

    /**
     * 'sold' no está aquí a propósito: es un estado derivado (borrado blando
     * + precio/fecha de venta), nunca asignable directamente por el usuario
     * — se marca solo desde SalesController::markAsSold().
     */
    public const STATUSES = ['owned', 'wishlist'];

    public const PLAY_STATUSES = ['pending', 'playing', 'finished'];

    public const MANUAL_STATUSES = ['included', 'missing', 'booklet'];

    public const RATING_MIN = 1;

    public const RATING_MAX = 5;

    // ==========================================
    // BÚSQUEDA
    // ==========================================

    /**
     * Coincidencia por título (parcial, sin distinguir mayúsculas) o EAN
     * (exacto). Mismo criterio usado por el buscador de la colección
     * (GameCollectionQuery::query) y por el Ctrl+K (SearchController::quick).
     *
     * @param  Builder<Game>  $query
     * @return Builder<Game>
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        return $query->where(function (Builder $q) use ($term) {
            $q->whereLike('title', '%'.$term.'%', caseSensitive: false)
                ->orWhere('ean', $term);
        });
    }

    // ==========================================
    // RELACIONES
    // ==========================================

    /**
     * Un juego pertenece a un único usuario.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Un juego pertenece a una plataforma.
     *
     * @return BelongsTo<Platform, $this>
     */
    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class);
    }

    /**
     * Un juego pertenece a una edición específica (Opcional).
     *
     * @return BelongsTo<Edition, $this>
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
        return $this->cover ? url('storage/'.$this->cover) : null;
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

    /**
     * igdb_time_to_beat en horas enteras en vez de segundos (formato crudo de
     * la API), para pintarlo en la ficha (ver games/show.blade.php). null si
     * no hay dato todavía (sin match, o el juego no tiene tiempos en IGDB).
     *
     * @return array{hastily?: int, normally?: int, completely?: int, count?: int}|null
     */
    public function igdbTimeToBeatHours(): ?array
    {
        if (! $this->igdb_time_to_beat) {
            return null;
        }

        /** @var array<string, int> $timeToBeat */
        $timeToBeat = $this->igdb_time_to_beat;

        return collect($timeToBeat)
            ->map(fn (int $value, string $key) => $key === 'count' ? $value : (int) round($value / 3600))
            ->all();
    }

    /**
     * URL del fondo elegido a mano entre las opciones de IGDB (ver
     * IgdbController::artworks()/setBackground()), o null si no se
     * ha elegido ninguno. Se guarda solo el image_id de IGDB, no la URL
     * completa, para poder pedir aquí el tamaño que convenga en cada sitio.
     */
    public function backgroundUrl(string $size = '1080p'): ?string
    {
        return $this->igdb_background ? "https://images.igdb.com/igdb/image/upload/t_{$size}/{$this->igdb_background}.jpg" : null;
    }
}
