@props(['platform'])

@if($platform)
    <span
        {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-semibold border']) }}
        style="background-color: {{ $platform->effectiveBgColor() }}; color: {{ $platform->effectiveTextColor() }}; border-color: {{ $platform->effectiveBorderColor() }};"
    >
        {{ $platform->chipLabel() }}
    </span>
@else
    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-semibold bg-slate-800 text-slate-400 border border-slate-700">
        Sin plataforma
    </span>
@endif
