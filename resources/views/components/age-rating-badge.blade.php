@props(['game'])

@php
    // Solo ficha de detalle por ahora (issue #46 deja fuera de alcance
    // listado/tarjetas) — componente aparte de todos modos, para no ensuciar
    // show.blade.php con la lógica condicional icono/pill/nada y dejarlo
    // listo si algún día se extiende.
    $badge = $game->ageRatingBadge();

    $severityClasses = [
        'green' => 'bg-emerald-600 text-white',
        'amber' => 'bg-amber-500 text-slate-950',
        'orange' => 'bg-orange-500 text-white',
        'red' => 'bg-red-600 text-white',
        'neutral' => 'bg-slate-700 text-slate-100',
    ];
@endphp

@if($badge)
    @if($badge['iconPath'])
        <img src="{{ $badge['iconPath'] }}" alt="{{ $badge['label'] }}" title="{{ $badge['label'] }}"
            {{ $attributes->merge(['class' => 'w-12 h-12 shrink-0 rounded-lg shadow-lg']) }}>
    @else
        <span title="{{ $badge['label'] }}"
            {{ $attributes->merge(['class' => 'inline-flex items-center justify-center shrink-0 px-2 py-1 rounded-lg text-xs font-bold leading-none shadow-lg whitespace-nowrap '.($severityClasses[$badge['severity']] ?? $severityClasses['neutral'])]) }}>
            {{ $badge['label'] }}
        </span>
    @endif
@endif
