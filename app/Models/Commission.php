<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Encargo: un juego que un amigo compra/envía para el usuario, o que el
 * usuario compra/envía para un amigo — logística de "quién tiene/debe qué",
 * no una venta ni parte de la colección hasta que se marca recibido (ver
 * CommissionController::resolve()). El registro se queda como histórico
 * para siempre, se resuelva en la dirección que se resuelva.
 */
class Commission extends Model
{
    use HasFactory;

    public const DIRECTION_OWED_TO_ME = 'owed_to_me';

    public const DIRECTION_OWED_BY_ME = 'owed_by_me';

    public const DIRECTIONS = [self::DIRECTION_OWED_TO_ME, self::DIRECTION_OWED_BY_ME];

    protected $fillable = [
        'user_id',
        'title',
        'platform_id',
        'counterparty_name',
        'direction',
        'price',
        'purchased_at',
        'resolved_at',
        'game_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'purchased_at' => 'date',
            'resolved_at' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class);
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function isResolved(): bool
    {
        return $this->resolved_at !== null;
    }

    /**
     * "Recibido el d/m/Y" o "Enviado el d/m/Y" según la dirección — evita
     * repetir este condicional en la vista. Null mientras esté pendiente.
     */
    public function resolvedLabel(): ?string
    {
        if ($this->resolved_at === null) {
            return null;
        }

        $verb = $this->direction === self::DIRECTION_OWED_BY_ME ? 'Enviado' : 'Recibido';

        return "{$verb} el {$this->resolved_at->format('d/m/Y')}";
    }
}
