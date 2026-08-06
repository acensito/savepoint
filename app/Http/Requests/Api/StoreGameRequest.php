<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

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
            'status' => ['nullable', 'string', 'max:50'],
            'play_status' => ['nullable', 'string', 'max:50'],
            'release_date' => ['nullable', 'date'],
            'genres' => ['nullable', 'array'], // Validamos que llegue como un array
            'rating' => ['nullable', 'integer', 'min:1', 'max:10'],
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