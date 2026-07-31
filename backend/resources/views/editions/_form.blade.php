@php
    $checkedPlatformIds = old('platform_ids', $edition?->platforms->pluck('id')->toArray() ?? []);
@endphp

<div>
    <label for="name" class="block font-medium text-sm text-slate-300 mb-1">Nombre</label>
    <input type="text" name="name" id="name" value="{{ old('name', $edition?->name ?? '') }}" required autofocus
        placeholder="Edición Coleccionista"
        class="w-full rounded-lg border border-slate-700 bg-slate-800 text-slate-100 px-4 py-2 focus:border-indigo-500 focus:ring-indigo-500 outline-none">
    @error('name') <span class="text-red-400 text-sm mt-1 block">{{ $message }}</span> @enderror
</div>

<div>
    <div class="flex items-center justify-between mb-2">
        <span class="block font-medium text-sm text-slate-300">Disponible en</span>
        <div class="flex items-center gap-3 text-xs font-medium">
            <button type="button" id="select-all-platforms" class="text-indigo-400 hover:text-indigo-300">Seleccionar todas</button>
            <button type="button" id="select-none-platforms" class="text-slate-400 hover:text-slate-100">Ninguna</button>
        </div>
    </div>
    <p class="text-xs text-slate-500 mb-3">Marca las plataformas donde existe esta edición. Si no marcas ninguna, se considerará disponible para cualquier plataforma.</p>

    <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 max-h-64 overflow-y-auto pr-2">
        @foreach($platforms as $platform)
            <label class="flex items-center gap-2 text-sm text-slate-300">
                <input type="checkbox" name="platform_ids[]" value="{{ $platform->id }}"
                    {{ in_array($platform->id, $checkedPlatformIds) ? 'checked' : '' }}
                    class="platform-checkbox rounded border-slate-600 bg-slate-800 text-indigo-600 focus:ring-indigo-500">
                {{ $platform->name }}
            </label>
        @endforeach
    </div>
    @error('platform_ids') <span class="text-red-400 text-sm mt-1 block">{{ $message }}</span> @enderror
</div>

<script>
    document.getElementById('select-all-platforms')?.addEventListener('click', () => {
        document.querySelectorAll('.platform-checkbox').forEach((checkbox) => { checkbox.checked = true; });
    });

    document.getElementById('select-none-platforms')?.addEventListener('click', () => {
        document.querySelectorAll('.platform-checkbox').forEach((checkbox) => { checkbox.checked = false; });
    });
</script>
