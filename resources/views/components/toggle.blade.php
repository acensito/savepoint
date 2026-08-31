@props(['name', 'checked' => false, 'id' => null, 'url'])

{{--
    Toggle switch de efecto/persistencia inmediatos (ver initSettingsToggles en
    app.js): el checkbox real va oculto (peer sr-only, mismo patrón que los
    swatches de color de navbar en settings.blade.php) y solo pinta el
    track/thumb de al lado. data-url/data-field identifican a qué endpoint y
    con qué nombre de campo guardar el PATCH {field, value} (ver
    PanelController::updateToggle y UserController::updateRegistration); el
    name/value se dejan puestos por si el checkbox viaja dentro de un <form>
    normal, pero ninguna ruta los procesa ya por ese lado.

    "flex" y no "inline-flex" en el <label>: varios toggles seguidos como
    hermanos directos (p. ej. la tarjeta "Secciones opcionales" de
    settings.blade.php) quedaban en línea unos junto a otros cuando cabían,
    en vez de uno por fila — "flex" es de caja de bloque, así que cada
    <label> ocupa su propia línea igual que cualquier otro elemento apilado
    con space-y-*.
--}}
<label {{ $attributes->merge(['class' => 'flex items-center gap-3 text-sm text-slate-300 cursor-pointer']) }}>
    <span class="relative inline-flex h-6 w-11 shrink-0 items-center">
        <input type="checkbox" id="{{ $id ?? $name }}" name="{{ $name }}" value="1" {{ $checked ? 'checked' : '' }}
            class="peer sr-only js-setting-toggle" data-url="{{ $url }}" data-field="{{ $name }}">
        <span class="absolute inset-0 rounded-full bg-slate-700 transition-colors peer-checked:bg-(--color-navbar) peer-focus-visible:ring-2 peer-focus-visible:ring-offset-2 peer-focus-visible:ring-offset-slate-900 peer-focus-visible:ring-indigo-500"></span>
        <span class="absolute left-1 h-4 w-4 rounded-full bg-white transition-transform peer-checked:translate-x-5"></span>
    </span>
    {{ $slot }}
</label>
