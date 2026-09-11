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

    // Iconos propios en SVG (issue #149): Material Symbols no cubre todo
    // (p.ej. cassette/cinta). Mismo prop 'name' que las ligaduras de fuente
    // para no romper el uso actual — si existe un SVG con ese nombre en
    // resources/svg/gicons/ se inlinea, si no se usa la ligadura de siempre.
    // El SVG debe usar fill/stroke="currentColor" para heredar color de
    // texto igual que la fuente.
    $svg = preg_match('/^[a-z0-9_-]+$/', $name) && is_file($path = resource_path("svg/gicons/{$name}.svg"))
        ? file_get_contents($path)
        : null;
@endphp

@if ($svg)
    <span
        {{ $attributes->except('style')->merge(['class' => 'gicon-svg align-middle', 'style' => $style]) }}
    >{!! $svg !!}</span>
@else
    <span
        {{ $attributes->except('style')->merge(['class' => 'material-symbols-outlined align-middle', 'style' => $style]) }}
    >{{ $name }}</span>
@endif
