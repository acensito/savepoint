@php
    $bg = old('bg_color', $manufacturer?->bg_color ?? '#EEF2FF');
    $text = old('text_color', $manufacturer?->text_color ?? '#4338CA');
    $border = old('border_color', $manufacturer?->border_color ?? '#C7D2FE');
@endphp

<div>
    <label for="name" class="block font-medium text-sm text-slate-300 mb-1">Nombre</label>
    <input type="text" name="name" id="name" value="{{ old('name', $manufacturer?->name ?? '') }}" required autofocus
        class="w-full rounded-lg border border-slate-700 bg-slate-800 text-slate-100 px-4 py-2 focus:border-indigo-500 focus:ring-indigo-500 outline-none">
    @error('name') <span class="text-red-400 text-sm mt-1 block">{{ $message }}</span> @enderror
</div>

<div class="grid grid-cols-3 gap-4">
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

<div>
    <span class="block font-medium text-sm text-slate-300 mb-1">Vista previa del chip</span>
    <span id="chip-preview" class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-semibold border"
        style="background-color: {{ $bg }}; color: {{ $text }}; border-color: {{ $border }};">
        {{ old('name', $manufacturer?->name ?? 'Nombre del fabricante') }}
    </span>
</div>

<script>
    function updateChipPreview() {
        const preview = document.getElementById('chip-preview');
        preview.style.backgroundColor = document.getElementById('bg_color').value;
        preview.style.color = document.getElementById('text_color').value;
        preview.style.borderColor = document.getElementById('border_color').value;
    }
    document.getElementById('name')?.addEventListener('input', (e) => {
        document.getElementById('chip-preview').textContent = e.target.value || 'Nombre del fabricante';
    });
</script>
