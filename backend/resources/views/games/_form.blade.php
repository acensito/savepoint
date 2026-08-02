@php
    $input = 'w-full rounded-lg border border-slate-700 bg-slate-800 text-slate-100 px-4 py-2 focus:border-indigo-500 focus:ring-indigo-500 outline-none';
    $label = 'block font-medium text-sm text-slate-300 mb-1';
    $error = 'text-red-400 text-sm mt-1 block';

    $defaultPurchaseDate = $game ? $game->purchase_date?->format('Y-m-d') : now()->format('Y-m-d');

    $regionPresets = ['PAL-ES', 'PAL-EU', 'PAL-UK', 'PAL-FR', 'PAL-DE', 'PAL-IT', 'NTSC-U', 'NTSC-J'];
    $defaultRegionSelect = $game ? ($game->region ?? '') : 'PAL-ES';
    $currentRegionSelect = old('region_select', $defaultRegionSelect);
    $isCustomRegion = $currentRegionSelect !== '' && $currentRegionSelect !== 'other' && !in_array($currentRegionSelect, $regionPresets, true);
    $regionSelectValue = $isCustomRegion ? 'other' : $currentRegionSelect;
@endphp

<!-- Carátula -->
<div class="flex items-start gap-6">
    <div id="cover-wrapper" class="w-24 flex-shrink-0">
        @if($game?->cover)
            <img id="cover-preview-img" src="{{ $game->coverUrl() }}" alt="Carátula" class="w-24 h-auto rounded-xl border border-slate-700">
        @else
            <div id="cover-preview-img" class="w-24 aspect-square rounded-xl flex items-center justify-center bg-slate-800 border border-slate-700 text-slate-400 font-bold text-2xl">
                <span id="cover-initials">{{ $game?->coverInitials() ?? '?' }}</span>
            </div>
        @endif
    </div>

    <div class="flex-1">
        <label for="cover" class="{{ $label }}">Carátula</label>
        <input type="file" name="cover" id="cover" accept="image/*"
            class="block w-full text-sm text-slate-400 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-slate-800 file:text-slate-200 hover:file:bg-slate-700 cursor-pointer">
        <p class="text-xs text-slate-500 mt-1">JPG, PNG o WEBP, máx. 1MB. Si no subes ninguna, se muestran las iniciales del título.</p>
        @error('cover') <span class="{{ $error }}">{{ $message }}</span> @enderror

        @if($game?->cover)
            <label class="flex items-center gap-2 text-sm text-slate-400 mt-2">
                <input type="checkbox" name="remove_cover" value="1" class="rounded border-slate-600 bg-slate-800 text-red-500 focus:ring-red-500">
                Quitar carátula actual
            </label>
        @endif
    </div>
</div>

<!-- Datos básicos -->
<div class="pt-6 border-t border-slate-800 space-y-4">
    <h2 class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Datos básicos</h2>

    <div>
        <label for="title" class="{{ $label }}">Título</label>
        <input type="text" name="title" id="title" value="{{ old('title', $game?->title) }}" required autofocus class="{{ $input }}">
        @error('title') <span class="{{ $error }}">{{ $message }}</span> @enderror
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <label for="ean" class="{{ $label }}">EAN</label>
            <input type="text" name="ean" id="ean" value="{{ old('ean', $game?->ean) }}" class="{{ $input }}">
            @error('ean')
                <span class="{{ $error }}">{{ $message }}</span>
                <label class="flex items-center gap-2 text-sm text-slate-400 mt-1.5">
                    <input type="checkbox" name="confirm_duplicate" value="1" {{ old('confirm_duplicate') ? 'checked' : '' }}
                        class="rounded border-slate-600 bg-slate-800 text-indigo-600 focus:ring-indigo-500">
                    Guardar de todos modos (ya tengo otra copia física)
                </label>
            @enderror
        </div>
        <div>
            <label for="developer" class="{{ $label }}">Desarrollador</label>
            <input type="text" name="developer" id="developer" value="{{ old('developer', $game?->developer) }}" class="{{ $input }}">
            @error('developer') <span class="{{ $error }}">{{ $message }}</span> @enderror
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <label for="platform_id" class="{{ $label }}">Plataforma</label>
            <select name="platform_id" id="platform_id" class="{{ $input }}">
                <option value="">Selecciona una plataforma</option>
                @foreach($platforms as $platform)
                    <option value="{{ $platform->id }}" {{ old('platform_id', $game?->platform_id) == $platform->id ? 'selected' : '' }}>
                        {{ $platform->name }}
                    </option>
                @endforeach
            </select>
            @error('platform_id') <span class="{{ $error }}">{{ $message }}</span> @enderror
        </div>
        <div>
            <label for="edition_id" class="{{ $label }}">Edición</label>
            <div class="flex gap-2">
                <select name="edition_id" id="edition_id" class="{{ $input }}">
                    <option value="">Sin edición específica</option>
                    @foreach($editions as $edition)
                        <option value="{{ $edition->id }}" data-platforms="{{ $edition->platforms->pluck('id')->implode(',') }}"
                            {{ old('edition_id', $game?->edition_id) == $edition->id ? 'selected' : '' }}>
                            {{ $edition->name }}
                        </option>
                    @endforeach
                </select>
                <button type="button" id="quick-add-edition-btn"
                    class="flex-shrink-0 flex items-center justify-center w-10 h-10 rounded-lg border border-slate-700 bg-slate-800 text-slate-300 hover:bg-slate-700 hover:text-white transition-colors"
                    aria-label="Crear una edición nueva">
                    <x-gicon name="add" class="text-[20px]" />
                </button>
            </div>
            <p class="text-xs text-slate-500 mt-1">Se filtra según la plataforma elegida. <a href="{{ route('web.editions.index') }}" class="text-indigo-400 hover:text-indigo-300">Gestionar ediciones</a>.</p>
            @error('edition_id') <span class="{{ $error }}">{{ $message }}</span> @enderror

            <dialog id="quick-add-edition-dialog" class="rounded-xl border border-slate-800 bg-slate-900 text-slate-100 p-0 backdrop:bg-black/60 w-full max-w-sm">
                <div class="p-5">
                    <h2 class="text-base font-semibold text-slate-100">Nueva edición</h2>
                    <p class="text-xs text-slate-500 mt-1">Se añade sin salir de este formulario; no se pierde lo que ya has rellenado. Podrás asociarla a plataformas concretas más tarde desde "Gestionar ediciones".</p>

                    <div class="mt-4">
                        <label for="quick-edition-name" class="{{ $label }}">Nombre</label>
                        <input type="text" id="quick-edition-name" placeholder="Edición Coleccionista" class="{{ $input }}">
                        <span id="quick-edition-error" class="text-red-400 text-sm mt-1 block"></span>
                    </div>

                    <div class="flex items-center justify-end gap-3 mt-5">
                        <button type="button" id="quick-add-edition-cancel" class="text-slate-400 hover:text-slate-100 text-sm font-medium px-4 py-2">Cancelar</button>
                        <button type="button" id="quick-add-edition-submit" class="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-indigo-500 transition-colors">Crear edición</button>
                    </div>
                </div>
            </dialog>
        </div>
    </div>

    <div>
        <label for="release_date" class="{{ $label }}">Fecha de lanzamiento</label>
        <input type="date" name="release_date" id="release_date" value="{{ old('release_date', $game?->release_date?->format('Y-m-d')) }}" class="{{ $input }} max-w-[220px]">
        @error('release_date') <span class="{{ $error }}">{{ $message }}</span> @enderror
    </div>

    <div>
        <label for="genres" class="{{ $label }}">Géneros</label>
        <input type="text" name="genres" id="genres" placeholder="Acción, Aventura, RPG"
            value="{{ old('genres', $game?->genres ? implode(', ', $game->genres) : '') }}" class="{{ $input }}">
        <p class="text-xs text-slate-500 mt-1">Sepáralos con comas.</p>
        @error('genres') <span class="{{ $error }}">{{ $message }}</span> @enderror
    </div>
</div>

<!-- Estado y conservación -->
<div class="pt-6 border-t border-slate-800 space-y-4">
    <h2 class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Estado y conservación</h2>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <label for="status" class="{{ $label }}">Propiedad</label>
            <select name="status" id="status" class="{{ $input }}">
                <option value="">—</option>
                <option value="owned" {{ old('status', $game ? $game->status : 'owned') == 'owned' ? 'selected' : '' }}>En colección</option>
                <option value="wishlist" {{ old('status', $game?->status) == 'wishlist' ? 'selected' : '' }}>Lista de deseos</option>
                <option value="sold" {{ old('status', $game?->status) == 'sold' ? 'selected' : '' }}>Vendido</option>
            </select>
            @error('status') <span class="{{ $error }}">{{ $message }}</span> @enderror
        </div>
        <div>
            <label for="play_status" class="{{ $label }}">Estado de juego</label>
            <select name="play_status" id="play_status" class="{{ $input }}">
                <option value="pending" {{ old('play_status', $game?->play_status) == 'pending' ? 'selected' : '' }}>Pendiente</option>
                <option value="playing" {{ old('play_status', $game?->play_status) == 'playing' ? 'selected' : '' }}>Jugando</option>
                <option value="finished" {{ old('play_status', $game?->play_status) == 'finished' ? 'selected' : '' }}>Terminado</option>
            </select>
            @error('play_status') <span class="{{ $error }}">{{ $message }}</span> @enderror
        </div>
    </div>

    <div>
        <label class="{{ $label }}">Conservación</label>
        <input type="hidden" name="rating" id="rating" value="{{ old('rating', $game?->rating) }}">
        <div class="flex items-center gap-3">
            <div id="rating-stars" class="flex items-center gap-1">
                @for ($i = 1; $i <= 5; $i++)
                    <button type="button" class="rating-star p-0.5 leading-none" data-value="{{ $i }}" aria-label="{{ $i }} estrella(s)">
                        <x-gicon name="star" class="text-[26px] text-slate-600 pointer-events-none" />
                    </button>
                @endfor
            </div>
            <span id="rating-label" class="text-sm text-slate-400"></span>
        </div>
        @error('rating') <span class="{{ $error }}">{{ $message }}</span> @enderror
    </div>
</div>

<!-- Compra -->
<div class="pt-6 border-t border-slate-800 space-y-4">
    <h2 class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Compra</h2>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div>
            <label for="price_paid" class="{{ $label }}">Precio pagado</label>
            <input type="number" step="0.01" min="0" name="price_paid" id="price_paid" value="{{ old('price_paid', $game?->price_paid) }}" class="{{ $input }}">
            @error('price_paid') <span class="{{ $error }}">{{ $message }}</span> @enderror
        </div>
        <div>
            <label for="purchase_place" class="{{ $label }}">Lugar de compra</label>
            <input type="text" name="purchase_place" id="purchase_place" value="{{ old('purchase_place', $game?->purchase_place) }}" class="{{ $input }}">
            @error('purchase_place') <span class="{{ $error }}">{{ $message }}</span> @enderror
        </div>
        <div>
            <label for="purchase_date" class="{{ $label }}">Fecha de compra</label>
            <input type="date" name="purchase_date" id="purchase_date" value="{{ old('purchase_date', $defaultPurchaseDate) }}" class="{{ $input }}">
            @error('purchase_date') <span class="{{ $error }}">{{ $message }}</span> @enderror
        </div>
    </div>
</div>

<!-- Detalles físicos -->
<div class="pt-6 border-t border-slate-800 space-y-4">
    <h2 class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Detalles físicos</h2>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div>
            <label for="manual_status" class="{{ $label }}">Manual</label>
            <select name="manual_status" id="manual_status" class="{{ $input }}">
                <option value="">--</option>
                <option value="included" {{ old('manual_status', $game?->manual_status) == 'included' ? 'selected' : '' }}>Con Manual</option>
                <option value="missing" {{ old('manual_status', $game?->manual_status) == 'missing' ? 'selected' : '' }}>Sin Manual</option>
                <option value="booklet" {{ old('manual_status', $game?->manual_status) == 'booklet' ? 'selected' : '' }}>Folleto</option>
            </select>
            @error('manual_status') <span class="{{ $error }}">{{ $message }}</span> @enderror
        </div>
        <div>
            <label for="region_select" class="{{ $label }}">Región</label>
            <select name="region_select" id="region_select" class="{{ $input }}" onchange="toggleCustomRegion()">
                <option value="" {{ $regionSelectValue === '' ? 'selected' : '' }}>Sin especificar</option>
                @foreach($regionPresets as $preset)
                    <option value="{{ $preset }}" {{ $regionSelectValue === $preset ? 'selected' : '' }}>{{ $preset }}</option>
                @endforeach
                <option value="other" {{ $regionSelectValue === 'other' ? 'selected' : '' }}>Otra…</option>
            </select>
            <input type="text" name="region_other" id="region_other" maxlength="50" placeholder="Especifica la región"
                value="{{ old('region_other', $isCustomRegion ? $currentRegionSelect : '') }}"
                class="{{ $input }} mt-2 {{ $regionSelectValue === 'other' ? '' : 'hidden' }}">
            @error('region_select') <span class="{{ $error }}">{{ $message }}</span> @enderror
            @error('region_other') <span class="{{ $error }}">{{ $message }}</span> @enderror
        </div>
        <div>
            <label for="age_rating" class="{{ $label }}">Clasificación por edad</label>
            <input type="text" name="age_rating" id="age_rating" placeholder="PEGI 12..." value="{{ old('age_rating', $game?->age_rating) }}" class="{{ $input }}">
            @error('age_rating') <span class="{{ $error }}">{{ $message }}</span> @enderror
        </div>
    </div>
</div>

<!-- Notas -->
<div class="pt-6 border-t border-slate-800">
    <label for="notes" class="{{ $label }}">Notas</label>
    <textarea name="notes" id="notes" rows="3" class="{{ $input }}">{{ old('notes', $game?->notes) }}</textarea>
    @error('notes') <span class="{{ $error }}">{{ $message }}</span> @enderror
</div>

<script>
    document.getElementById('cover')?.addEventListener('change', (e) => {
        const file = e.target.files[0];
        if (!file) return;
        document.getElementById('cover-wrapper').innerHTML =
            `<img id="cover-preview-img" src="${URL.createObjectURL(file)}" alt="Carátula" class="w-24 h-auto rounded-xl border border-slate-700">`;
    });

    document.getElementById('title')?.addEventListener('input', (e) => {
        const initialsEl = document.getElementById('cover-initials');
        if (!initialsEl) return;
        const words = e.target.value.trim().split(/\s+/).filter(Boolean);
        initialsEl.textContent = words.slice(0, 2).map(w => w[0].toUpperCase()).join('') || '?';
    });

    function toggleCustomRegion() {
        const isOther = document.getElementById('region_select').value === 'other';
        document.getElementById('region_other').classList.toggle('hidden', !isOther);
    }

    function filterEditions() {
        const platformId = document.getElementById('platform_id').value;
        const editionSelect = document.getElementById('edition_id');

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

    document.getElementById('platform_id')?.addEventListener('change', filterEditions);

    (function () {
        const dialog = document.getElementById('quick-add-edition-dialog');
        const openBtn = document.getElementById('quick-add-edition-btn');
        const cancelBtn = document.getElementById('quick-add-edition-cancel');
        const submitBtn = document.getElementById('quick-add-edition-submit');
        const nameInput = document.getElementById('quick-edition-name');
        const errorEl = document.getElementById('quick-edition-error');
        if (!dialog || !openBtn) return;

        openBtn.addEventListener('click', () => {
            errorEl.textContent = '';
            nameInput.value = '';
            dialog.showModal();
            nameInput.focus();
        });

        cancelBtn.addEventListener('click', () => dialog.close());

        submitBtn.addEventListener('click', async () => {
            const name = nameInput.value.trim();
            if (!name) {
                errorEl.textContent = 'El nombre es obligatorio.';
                return;
            }

            submitBtn.disabled = true;
            errorEl.textContent = '';

            try {
                // La edición se crea al vuelo por AJAX precisamente para no
                // navegar a /editions/create y perder lo ya rellenado en este
                // formulario de alta/edición de juego.
                const response = await fetch('{{ route('web.editions.store') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('input[name="_token"]')?.value ?? '',
                    },
                    body: JSON.stringify({ name }),
                });

                if (!response.ok) {
                    const data = await response.json().catch(() => ({}));
                    errorEl.textContent = data.errors?.name?.[0] ?? 'No se pudo crear la edición.';
                    return;
                }

                const edition = await response.json();

                const select = document.getElementById('edition_id');
                const option = document.createElement('option');
                option.value = edition.id;
                option.textContent = edition.name;
                option.dataset.platforms = '';
                select.appendChild(option);
                select.value = edition.id;

                dialog.close();
            } catch (err) {
                errorEl.textContent = 'No se pudo crear la edición. Comprueba tu conexión e inténtalo de nuevo.';
            } finally {
                submitBtn.disabled = false;
            }
        });
    })();

    (function () {
        const ratingLabels = { 1: 'Malo', 2: 'Regular', 3: 'Bueno', 4: 'Muy bueno', 5: 'Nuevo / precintado' };
        const ratingInput = document.getElementById('rating');
        const ratingButtons = document.querySelectorAll('.rating-star');
        const ratingLabelEl = document.getElementById('rating-label');
        if (!ratingInput || !ratingButtons.length) return;

        function paintRating(value) {
            // El color depende del valor elegido en conjunto (1 = rojo ... 5 = amarillo),
            // no de la posición de cada estrella: si eliges 3, las tres se pintan naranja.
            const hue = value >= 1 ? (value - 1) * 15 : 0;
            ratingButtons.forEach((btn) => {
                const starValue = Number(btn.dataset.value);
                const icon = btn.querySelector('.material-symbols-outlined');
                if (starValue <= value) {
                    icon.style.color = `hsl(${hue} 85% 55%)`;
                    icon.style.fontVariationSettings = "'FILL' 1";
                } else {
                    icon.style.color = '';
                    icon.style.fontVariationSettings = "'FILL' 0";
                }
            });
            ratingLabelEl.textContent = ratingLabels[value] || '';
        }

        ratingButtons.forEach((btn) => {
            btn.addEventListener('mouseenter', () => paintRating(Number(btn.dataset.value)));
            btn.addEventListener('click', () => {
                ratingInput.value = btn.dataset.value;
                paintRating(Number(ratingInput.value));
            });
        });

        document.getElementById('rating-stars').addEventListener('mouseleave', () => {
            paintRating(Number(ratingInput.value) || 0);
        });

        paintRating(Number(ratingInput.value) || 0);
    })();
    filterEditions();
</script>
