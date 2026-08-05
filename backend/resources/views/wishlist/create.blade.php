@extends('layouts.app')

@section('content')
    @php
        $input = 'w-full rounded-lg border border-slate-700 bg-slate-800 text-slate-100 px-4 py-2 focus:border-indigo-500 focus:ring-indigo-500 outline-none';
        $label = 'block font-medium text-sm text-slate-300 mb-1';
        $error = 'text-red-400 text-sm mt-1 block';
    @endphp

    <div class="max-w-2xl mx-auto py-6">
        <div class="mb-8 flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-slate-100 tracking-tight">Añadir a la lista de deseos</h1>
                <p class="text-slate-400 mt-1">Solo lo justo para no perderlo de vista. El resto de datos (precio, conservación...) se rellenan al pasarlo a la colección.</p>
            </div>
            <a href="{{ route('web.wishlist.index') }}" class="text-sm font-medium text-slate-400 hover:text-slate-100 whitespace-nowrap">
                ← Volver a la wishlist
            </a>
        </div>

        <div class="bg-slate-900 border border-slate-800 rounded-xl p-8">
            <form action="{{ route('web.wishlist.store') }}" method="POST" class="space-y-4">
                @csrf

                <div>
                    <label for="title" class="{{ $label }}">Título</label>
                    <input type="text" name="title" id="title" value="{{ old('title') }}" required autofocus class="{{ $input }}">
                    @error('title') <span class="{{ $error }}">{{ $message }}</span> @enderror
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="platform_id" class="{{ $label }}">Plataforma</label>
                        <select name="platform_id" id="platform_id" class="{{ $input }}">
                            <option value="">Selecciona una plataforma</option>
                            @foreach($platforms as $platform)
                                <option value="{{ $platform->id }}" {{ old('platform_id') == $platform->id ? 'selected' : '' }}>
                                    {{ $platform->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('platform_id') <span class="{{ $error }}">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label for="edition_id" class="{{ $label }}">Edición</label>
                        <select name="edition_id" id="edition_id" class="{{ $input }}">
                            <option value="">Sin edición específica</option>
                            @foreach($editions as $edition)
                                <option value="{{ $edition->id }}" data-platforms="{{ $edition->platforms->pluck('id')->implode(',') }}"
                                    {{ old('edition_id') == $edition->id ? 'selected' : '' }}>
                                    {{ $edition->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('edition_id') <span class="{{ $error }}">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-800">
                    <a href="{{ route('web.wishlist.index') }}" class="text-slate-400 hover:text-slate-100 text-sm font-medium px-4 py-2">Cancelar</a>
                    <button type="submit" class="bg-indigo-600 text-white px-5 py-2.5 rounded-lg text-sm font-medium hover:bg-indigo-500 transition-colors">
                        Añadir a la wishlist
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        (function () {
            const platformSelect = document.getElementById('platform_id');
            const editionSelect = document.getElementById('edition_id');
            if (!platformSelect || !editionSelect) return;

            function filterEditions() {
                const platformId = platformSelect.value;
                Array.from(editionSelect.options).forEach((opt) => {
                    if (!opt.value) {
                        opt.hidden = false;
                        return;
                    }
                    const platforms = (opt.dataset.platforms || '').split(',').filter(Boolean);
                    opt.hidden = platforms.length > 0 && !!platformId && !platforms.includes(platformId);
                });
                if (editionSelect.selectedOptions[0]?.hidden) {
                    editionSelect.value = '';
                }
            }

            platformSelect.addEventListener('change', filterEditions);
            filterEditions();
        })();
    </script>
@endsection
