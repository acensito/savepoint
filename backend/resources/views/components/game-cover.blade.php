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
    <img src="{{ $game->coverUrl() }}" alt="{{ $game->title }}"
        {{ $attributes->merge(['class' => "$width h-auto $rounded border border-slate-700 flex-shrink-0"]) }}>
@else
    <div {{ $attributes->merge(['class' => "$width aspect-square $textSize $rounded flex items-center justify-center bg-slate-800 border border-slate-700 text-slate-400 font-bold flex-shrink-0"]) }}>
        {{ $game?->coverInitials() ?? '?' }}
    </div>
@endif
