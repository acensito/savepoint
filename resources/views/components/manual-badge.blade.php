{{--
    Badge de manual_status, antes copiado byte a byte entre la tabla y la
    tarjeta de games/_results.blade.php (issue #188, auditoría de
    mantenibilidad del 2026-09-10). showDash: solo la tabla pintaba un "—"
    para "sin dato" (la tarjeta simplemente no mostraba nada ahí), así que se
    deja como opción en vez de forzar el mismo comportamiento en las dos.
--}}
@props(['status', 'showDash' => false])

@if($status === 'included')
    <span {{ $attributes->merge(['class' => 'font-semibold text-emerald-400']) }}>CON MANUAL</span>
@elseif($status === 'booklet')
    <span {{ $attributes->merge(['class' => 'font-semibold text-emerald-400']) }}>CON FOLLETO</span>
@elseif($status === 'missing')
    <span {{ $attributes->merge(['class' => 'font-semibold text-amber-400']) }}>FALTA</span>
@elseif($showDash)
    <span {{ $attributes->merge(['class' => 'text-slate-500']) }}>—</span>
@endif
