@php
    $hasOverride = old('override_colors', $platform?->bg_color !== null) ? true : false;
    $bg = old('bg_color', $platform?->bg_color ?? '#EEF2FF');
    $text = old('text_color', $platform?->text_color ?? '#4338CA');
    $border = old('border_color', $platform?->border_color ?? '#C7D2FE');
@endphp

<div>
    <label for="name" class="block font-medium text-sm text-slate-300 mb-1">Nombre</label>
    <input type="text" name="name" id="name" value="{{ old('name', $platform?->name ?? '') }}" required autofocus
        placeholder="PlayStation 5"
        class="w-full rounded-lg border border-slate-700 bg-slate-800 text-slate-100 px-4 py-2 focus:border-indigo-500 focus:ring-indigo-500 outline-none">
    @error('name') <span class="text-red-400 text-sm mt-1 block">{{ $message }}</span> @enderror
</div>

<div>
    <label for="label" class="block font-medium text-sm text-slate-300 mb-1">Etiqueta abreviada (chip)</label>
    <input type="text" name="label" id="label" value="{{ old('label', $platform?->label ?? '') }}"
        placeholder="PS5" maxlength="20"
        class="w-full rounded-lg border border-slate-700 bg-slate-800 text-slate-100 px-4 py-2 focus:border-indigo-500 focus:ring-indigo-500 outline-none">
    <p class="text-xs text-slate-500 mt-1">Si la dejas vacía, el chip muestra el nombre completo.</p>
    @error('label') <span class="text-red-400 text-sm mt-1 block">{{ $message }}</span> @enderror
</div>

<div>
    <label for="manufacturer_id" class="block font-medium text-sm text-slate-300 mb-1">Fabricante</label>
    <select name="manufacturer_id" id="manufacturer_id"
        class="w-full rounded-lg border border-slate-700 bg-slate-800 text-slate-100 px-4 py-2 focus:border-indigo-500 focus:ring-indigo-500 outline-none">
        <option value="">Sin fabricante</option>
        @foreach($manufacturers as $manufacturer)
            <option value="{{ $manufacturer->id }}" {{ old('manufacturer_id', $platform?->manufacturer_id) == $manufacturer->id ? 'selected' : '' }}>
                {{ $manufacturer->name }}
            </option>
        @endforeach
    </select>
    <p class="text-xs text-slate-500 mt-1">Si no personalizas los colores abajo, el chip usa los del fabricante.</p>
    @error('manufacturer_id') <span class="text-red-400 text-sm mt-1 block">{{ $message }}</span> @enderror
</div>

<div class="pt-2 border-t border-slate-800">
    <label class="flex items-center gap-2 text-sm font-medium text-slate-300 mt-4">
        <input type="checkbox" name="override_colors" id="override_colors" value="1" {{ $hasOverride ? 'checked' : '' }}
            class="rounded border-slate-600 bg-slate-800 text-indigo-600 focus:ring-indigo-500" onchange="toggleColorOverride()">
        Personalizar colores para esta plataforma
    </label>

    <div id="color-fields" class="grid grid-cols-2 sm:grid-cols-3 gap-4 mt-4 {{ $hasOverride ? '' : 'hidden' }}">
        <div>
            <label for="bg_color" class="block font-medium text-sm text-slate-300 mb-1">Fondo</label>
            <input type="color" name="bg_color" id="bg_color" value="{{ $bg }}"
                class="w-full h-10 rounded-lg border border-slate-700 bg-slate-800 cursor-pointer" oninput="updateChipPreview()">
            @error('bg_color') <span class="text-red-400 text-sm mt-1 block">{{ $message }}</span> @enderror
        </div>
        <div>
            <label for="text_color" class="block font-medium text-sm text-slate-300 mb-1">Letras</label>
            <input type="color" name="text_color" id="text_color" value="{{ $text }}"
                class="w-full h-10 rounded-lg border border-slate-700 bg-slate-800 cursor-pointer" oninput="updateChipPreview()">
            @error('text_color') <span class="text-red-400 text-sm mt-1 block">{{ $message }}</span> @enderror
        </div>
        <div>
            <label for="border_color" class="block font-medium text-sm text-slate-300 mb-1">Borde</label>
            <input type="color" name="border_color" id="border_color" value="{{ $border }}"
                class="w-full h-10 rounded-lg border border-slate-700 bg-slate-800 cursor-pointer" oninput="updateChipPreview()">
            @error('border_color') <span class="text-red-400 text-sm mt-1 block">{{ $message }}</span> @enderror
        </div>
    </div>
</div>

<div>
    <span class="block font-medium text-sm text-slate-300 mb-1">Vista previa del chip</span>
    <span id="chip-preview" class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-semibold border"
        style="background-color: {{ $bg }}; color: {{ $text }}; border-color: {{ $border }};">
        {{ old('label', $platform?->chipLabel() ?? 'PS5') }}
    </span>
</div>

<script>
    function toggleColorOverride() {
        const enabled = document.getElementById('override_colors').checked;
        document.getElementById('color-fields').classList.toggle('hidden', !enabled);
        updateChipPreview();
    }

    function updateChipPreview() {
        const enabled = document.getElementById('override_colors').checked;
        const preview = document.getElementById('chip-preview');
        const manufacturerSelect = document.getElementById('manufacturer_id');
        const manufacturerColors = window.manufacturerColors || {};

        if (enabled) {
            preview.style.backgroundColor = document.getElementById('bg_color').value;
            preview.style.color = document.getElementById('text_color').value;
            preview.style.borderColor = document.getElementById('border_color').value;
        } else {
            const colors = manufacturerColors[manufacturerSelect.value] || { bg: '#EEF2FF', text: '#4338CA', border: '#C7D2FE' };
            preview.style.backgroundColor = colors.bg;
            preview.style.color = colors.text;
            preview.style.borderColor = colors.border;
        }
    }

    window.manufacturerColors = {
        @foreach($manufacturers as $manufacturer)
            "{{ $manufacturer->id }}": { bg: "{{ $manufacturer->bg_color }}", text: "{{ $manufacturer->text_color }}", border: "{{ $manufacturer->border_color }}" },
        @endforeach
    };

    document.getElementById('manufacturer_id')?.addEventListener('change', updateChipPreview);
    document.getElementById('label')?.addEventListener('input', (e) => {
        document.getElementById('chip-preview').textContent = e.target.value || document.getElementById('name').value || 'Nombre';
    });
    document.getElementById('name')?.addEventListener('input', (e) => {
        if (!document.getElementById('label').value) {
            document.getElementById('chip-preview').textContent = e.target.value || 'Nombre';
        }
    });
</script>
