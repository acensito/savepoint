{{--
    Trofeo de "Terminado" sobre la carátula, antes copiado entre la tarjeta y
    la estantería de games/_results.blade.php (issue #188, auditoría de
    mantenibilidad del 2026-09-10) con la sola diferencia real de en qué
    esquina va — se sigue decidiendo en cada sitio vía $attributes (posición,
    y el ring-2 que solo llevaba la tarjeta, para separarlo del borde de la
    carátula al solaparse con ella).
--}}
<span {{ $attributes->merge(['class' => 'flex items-center justify-center w-5 h-5 rounded-full bg-yellow-500 text-slate-950']) }} title="Terminado">
    <x-gicon name="emoji_events" :filled="true" class="text-[12px]" />
</span>
