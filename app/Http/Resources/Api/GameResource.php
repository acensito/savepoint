<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
