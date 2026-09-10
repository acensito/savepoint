@props(['game', 'size' => 'sm'])

@php
    // Ancho fijo, sin forzar una altura concreta: una carátula "portrait" (la
    // mayoría de cajas de videojuego) crece en alto según su proporción real
    // en vez de recortarse, y una prácticamente cuadrada (caja de CD/PC) sale
    // cuadrada sola, sin necesidad de distinguir el caso a mano.
    $width = $size === 'lg' ? 'w-24' : 'w-10';
    $textSize = $size === 'lg' ? 'text-2xl' : 'text-xs';
    $rounded = $size === 'lg' ? 'rounded-xl' : 'rounded-lg';
@endphp

@if($game?->cover)
    {{-- loading="lazy": con listados de 100 juegos/página, cargar las 100
         carátulas de golpe (auditoría de rendimiento del 2026-09-10, ~56 KB
         de media cada una) es la parte más cara de la página — el navegador
         difiere las que quedan fuera de la ventana visible sin que haga
         falta ninguna librería. Sin width/height fijos a propósito (ver
         comentario de arriba: cada carátula crece con su proporción real). --}}
    <img src="{{ $game->coverUrl() }}" alt="{{ $game->title }}" loading="lazy" decoding="async"
        {{ $attributes->merge(['class' => "$width h-auto $rounded border border-slate-700 shrink-0"]) }}>
@else
    @php $colors = $game?->coverPlaceholderColors() ?? ['bg' => '#1e293b', 'text' => '#94a3b8', 'border' => '#334155']; @endphp
    <div
        {{ $attributes->merge(['class' => "$width aspect-square $textSize $rounded flex items-center justify-center border font-bold shrink-0"]) }}
        style="background-color: {{ $colors['bg'] }}; color: {{ $colors['text'] }}; border-color: {{ $colors['border'] }};"
    >
        {{ $game?->coverInitials() ?? '?' }}
    </div>
@endif
