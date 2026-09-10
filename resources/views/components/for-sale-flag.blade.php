{{--
    Icono "en venta" junto al título, antes copiado entre la tabla y la
    tarjeta de games/_results.blade.php (issue #188, auditoría de
    mantenibilidad del 2026-09-10). No se usa en la estantería: ahí "en
    venta" es un badge sobre la carátula, no un icono junto al título — forma
    distinta de verdad, no la misma duplicada.
--}}
@props(['game'])

@if($game->for_sale)
    <x-gicon name="sell" {{ $attributes->merge(['class' => 'text-amber-400']) }} title="En venta" />
@endif
