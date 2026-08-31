<?php

namespace App\Http\Requests\Api;

use App\Models\Game;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGameRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'platform_id' => ['sometimes', 'required', 'exists:platforms,id'],
            // Ver StoreGameRequest: mismo rango/enums que el formulario web,
            // vía las constantes de Game.
            'status' => ['nullable', 'string', Rule::in(Game::STATUSES)],
            'play_status' => ['nullable', 'string', Rule::in(Game::PLAY_STATUSES)],
            'release_date' => ['nullable', 'date'],
            'genres' => ['nullable', 'array'],
            'rating' => ['nullable', 'integer', 'min:'.Game::RATING_MIN, 'max:'.Game::RATING_MAX],
            // max:99999999.99: tope real de la columna decimal(10,2) — ver
            // GameController::validated() para el mismo motivo.
            'price_paid' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
        ];
    }
}
