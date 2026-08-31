<?php

namespace App\Http\Resources\Api;

use App\Models\Game;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * JsonResource reenvía por __get() cualquier propiedad no definida aquí al
 * modelo envuelto ($this->resource): @mixin le dice a PHPStan que trate los
 * accesos a propiedades de esta clase como si fueran también las de Game.
 *
 * @mixin Game
 */
class GameResource extends JsonResource
{
    /**
     * Transforma el recurso en un array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'ean' => $this->ean,
            'cover_url' => $this->cover ? url('storage/'.$this->cover) : null,
            'status' => $this->status,
            'for_sale' => $this->for_sale,
            'play_status' => $this->play_status,
            // Aplanamos la relación para que a Flutter le llegue el nombre directamente
            'platform' => $this->whenLoaded('platform', fn () => $this->platform->name),
            'genres' => $this->genres,
            'rating' => $this->rating,
            'release_date' => $this->release_date?->format('Y-m-d'),
        ];
    }
}
