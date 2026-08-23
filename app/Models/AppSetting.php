<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Ajustes de instancia (una única fila, creada por la migración), no de
 * cuenta — a diferencia de todo lo que vive en panel/settings.
 */
#[Fillable(['registration_enabled'])]
class AppSetting extends Model
{
    protected function casts(): array
    {
        return [
            'registration_enabled' => 'boolean',
        ];
    }

    /**
     * firstOrCreate() en vez de asumir que la fila de la migración siempre
     * está ahí (defensivo, sin más motivo que ese): el valor por defecto de
     * creación es "abierto", el mismo comportamiento que tenía la app antes
     * de que existiera este ajuste.
     */
    public static function current(): self
    {
        return static::query()->firstOrCreate([], ['registration_enabled' => true]);
    }
}
