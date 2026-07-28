@props(['name', 'filled' => false])

<span
    {{ $attributes->merge(['class' => 'material-symbols-outlined align-middle']) }}
    @if($filled) style="font-variation-settings: 'FILL' 1" @endif
>{{ $name }}</span>
