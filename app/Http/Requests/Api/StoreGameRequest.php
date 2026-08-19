<?php

namespace App\Http\Requests\Api;

use App\Models\Game;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGameRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado a hacer esta petición.
     */
    public function authorize(): bool
    {
        // De momento lo dejamos en true. Más adelante, cuando implementemos Sanctum, 
        // aquí podríamos comprobar si el usuario tiene permisos específicos.
        return true; 
    }

    /**
     * Las reglas de validación que deben cumplir los datos enviados.
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'platform_id' => ['required', 'exists:platforms,id'], // Debe existir en la tabla platforms
            // Mismo rango/enums que el formulario web (GameController::validated()),
            // vía las constantes de Game: antes divergían (rating 1-10 aquí,
            // 1-5 en la web; status/play_status como string libre aquí,
            // enums cerrados en la web) y un alta por API fuera de esos
            // rangos rendería raro en la web.
            'status' => ['nullable', 'string', Rule::in(Game::STATUSES)],
            'play_status' => ['nullable', 'string', Rule::in(Game::PLAY_STATUSES)],
            'release_date' => ['nullable', 'date'],
            'genres' => ['nullable', 'array'], // Validamos que llegue como un array
            'rating' => ['nullable', 'integer', 'min:' . Game::RATING_MIN, 'max:' . Game::RATING_MAX],
            'price_paid' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * Mensajes personalizados de error (Opcional, pero muy útil para la app).
     */
    public function messages(): array
    {
        return [
            'title.required' => 'El título del juego es obligatorio.',
            'platform_id.exists' => 'La plataforma seleccionada no es válida.',
        ];
    }
}