{{--
    Casilla de selección en bloque, antes copiada tres veces entre tabla/
    tarjeta/estantería de games/_results.blade.php con solo el color y la
    posición cambiando entre las tres (issue #188, auditoría de
    mantenibilidad del 2026-09-10). variant="overlay": la estantería la pinta
    encima de la carátula, con un fondo semitransparente en vez del gris
    plano de las otras dos — el resto de clases (posición, tamaño de fuente
    del icono...) sigue viniendo del $attributes de cada sitio.
--}}
@props(['game', 'variant' => 'default'])

@php
    $colorClasses = match ($variant) {
        'overlay' => 'border-slate-500 bg-slate-900/80',
        default => 'border-slate-600 bg-slate-800',
    };
@endphp

<input type="checkbox" form="bulk-form" name="game_ids[]" value="{{ $game->id }}"
    {{ $attributes->merge(['class' => "js-bulk-checkbox w-4 h-4 rounded {$colorClasses} text-indigo-600 focus:ring-indigo-500"]) }}
    aria-label="Seleccionar {{ $game->title }}">
