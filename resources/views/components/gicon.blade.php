@props(['name', 'filled' => false])

@php
    // No usamos $attributes->merge() para 'style' directamente: si el que
    // llama al componente también pasa un style="...", acabaríamos con dos
    // atributos style="" en el mismo <span> y el navegador descarta el
    // segundo, perdiendo el FILL. Los combinamos aquí en uno solo.
    $style = trim(collect([
        $filled ? "font-variation-settings: 'FILL' 1" : null,
        $attributes->get('style'),
    ])->filter()->implode('; '));
@endphp

<span
    {{ $attributes->except('style')->merge(['class' => 'material-symbols-outlined align-middle', 'style' => $style]) }}
>{{ $name }}</span>
