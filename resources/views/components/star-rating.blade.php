@props(['rating' => null, 'size' => 'text-[13px]'])

@php
    $rating = (int) ($rating ?? 0);
    // Degradado según el valor elegido: 1 = rojo (hue 0) ... 5 = amarillo (hue 60).
    // Las estrellas rellenas comparten todas el color del valor, no una por posición.
    $hues = [0, 15, 30, 45, 60];
    $hue = $rating >= 1 && $rating <= 5 ? $hues[$rating - 1] : null;
@endphp

<div {{ $attributes->merge(['class' => 'inline-flex items-center gap-px']) }}>
    @for ($i = 1; $i <= 5; $i++)
        @if($i <= $rating)
            <x-gicon name="star" filled class="{{ $size }}" style="color: hsl({{ $hue }} 85% 55%)" />
        @else
            <x-gicon name="star" class="{{ $size }} text-slate-600" />
        @endif
    @endfor
</div>
