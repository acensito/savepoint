@props(['game', 'size' => 'sm'])

@php
    $dimensions = $size === 'lg' ? 'w-24 h-24 text-2xl rounded-xl' : 'w-10 h-10 text-xs rounded-lg';
@endphp

@if($game?->cover)
    <img src="{{ $game->coverUrl() }}" alt="{{ $game->title }}"
        {{ $attributes->merge(['class' => "$dimensions object-cover border border-slate-700 flex-shrink-0"]) }}>
@else
    <div {{ $attributes->merge(['class' => "$dimensions flex items-center justify-center bg-slate-800 border border-slate-700 text-slate-400 font-bold flex-shrink-0"]) }}>
        {{ $game?->coverInitials() ?? '?' }}
    </div>
@endif
