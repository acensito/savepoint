@php
    $input = 'w-full rounded-lg border border-slate-700 bg-slate-800 text-slate-100 px-4 py-2 focus:border-indigo-500 focus:ring-indigo-500 outline-none';
    $label = 'block font-medium text-sm text-slate-300 mb-1';
    $error = 'text-red-400 text-sm mt-1 block';

    // Solo llega en el alta (nunca en la edición, donde ya hay un $game real):
    // viene de la búsqueda rápida (Ctrl+K) cuando un EAN escaneado/buscado no
    // coincide con ningún juego existente, para no tener que volver a
    // teclearlo aquí.
    $prefill ??= [];

    // Llega en true desde la acción "Pasar a la colección" de la wishlist
    // (GameController::edit, ?convert_to_owned=1): mismo formulario de
    // siempre, pero con Propiedad y fecha de compra ya puestas a "En
    // colección"/hoy en vez de mostrar todavía "Lista de deseos".
    $convertToOwned ??= false;

    $defaultStatus = $convertToOwned ? 'owned' : ($game ? $game->status : 'owned');

    // Solo al dar de alta (nunca editando, donde ya hay una edición elegida o
    // deliberadamente ninguna): precarga la edición por defecto configurada
    // en Ajustes (ver PanelController::updateSettings), si el usuario tiene
    // una elegida.
    $defaultEditionId = $game ? $game->edition_id : auth()->user()?->default_edition_id;
    $defaultPurchaseDate = $game?->purchase_date?->format('Y-m-d')
        ?? ($convertToOwned || !$game ? now()->format('Y-m-d') : null);

    $regionPresets = \App\Http\Controllers\Web\GameController::REGION_PRESETS;
    // Misma idea que $defaultEditionId: región por defecto de Ajustes al dar
    // de alta, vacío ("Sin especificar") si no la ha configurado.
    $defaultRegionSelect = $game ? ($game->region ?? '') : (auth()->user()?->default_region ?? '');
    $currentRegionSelect = old('region_select', $defaultRegionSelect);
    $isCustomRegion = $currentRegionSelect !== '' && $currentRegionSelect !== 'other' && !in_array($currentRegionSelect, $regionPresets, true);
    $regionSelectValue = $isCustomRegion ? 'other' : $currentRegionSelect;

    // Carátula sugerida desde la ficha de comprobación de una búsqueda
    // externa (CEX): solo aplica al alta ($game null), nunca a la edición.
    // No se descarga hasta que se guarda el formulario (GameController::store).
    $externalCoverUrl = $game ? null : ($prefill['cover_url'] ?? null);
@endphp

<!-- Carátula -->
<div class="flex items-start gap-6">
    <div id="cover-wrapper" class="w-24 flex-shrink-0">
        @if($game?->cover)
            <img id="cover-preview-img" src="{{ $game->coverUrl() }}" alt="Carátula" class="w-24 h-auto rounded-xl border border-slate-700">
        @elseif($externalCoverUrl)
            <img id="cover-preview-img" src="{{ $externalCoverUrl }}" alt="Carátula" class="w-24 h-auto rounded-xl border border-slate-700">
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
        <p class="text-xs text-slate-500 mt-1">
            @if($externalCoverUrl)
                Carátula sugerida desde CEX. Sube un fichero aquí si prefieres reemplazarla.
            @else
                JPG, PNG o WEBP, máx. 1MB. Si no subes ninguna, se muestran las iniciales del título.
            @endif
        </p>
        @error('cover') <span class="{{ $error }}">{{ $message }}</span> @enderror

        <input type="hidden" name="cover_url" id="cover_url_input" value="{{ $externalCoverUrl }}">

        @if($game?->cover || $externalCoverUrl)
            <label class="flex items-center gap-2 text-sm text-slate-400 mt-2">
                <input type="checkbox" name="remove_cover" value="1" class="rounded border-slate-600 bg-slate-800 text-red-500 focus:ring-red-500">
                Quitar carátula {{ $game?->cover ? 'actual' : 'sugerida' }}
            </label>
        @endif
    </div>
</div>

<!-- Fuera de la columna estrecha de arriba (junto a la miniatura) para
     que ocupe el ancho completo del formulario, igual que el resto de
     campos (Título, EAN...) en vez de quedar desplazado a la derecha.
     Misma búsqueda en alta y edición: en el alta no hay todavía un Game
     guardado al que atar la ruta, así que usa un endpoint aparte sin
     autorización por juego (ver GameController::coverLookupForNew) y la
     búsqueda por defecto sale del EAN/título ya tecleados en el propio
     formulario en vez de una fila de la base de datos. -->
<div class="mt-3">
    <button type="button" id="cex-cover-lookup-btn"
        data-url="{{ $game ? route('web.games.cover-lookup', $game) : route('web.games.cover-lookup.new') }}"
        class="inline-flex items-center gap-1.5 text-xs font-medium text-indigo-400 hover:text-indigo-300">
        <x-gicon name="search" class="text-[14px]" />
        Buscar carátula y EAN en CEX
    </button>
    <p id="cex-cover-status" class="hidden text-xs text-slate-500 mt-1.5"></p>
    <ul id="cex-cover-results" class="hidden mt-1.5 space-y-1 max-h-48 overflow-y-auto rounded-lg border border-slate-700 bg-slate-800/50 p-1.5"></ul>

    <!-- Búsqueda manual: se muestra sin resultados (título mal escrito,
         subtítulo distinto al que usa CEX...) o para refinar una
         búsqueda que sí dio resultados pero no el esperado. -->
    <div id="cex-cover-manual" class="hidden mt-1.5 flex gap-1.5">
        <input type="text" id="cex-cover-manual-input" placeholder="Buscar con otras palabras…"
            autocomplete="off" autocorrect="off" spellcheck="false"
            class="flex-1 min-w-0 rounded-lg border border-slate-700 bg-slate-800 text-slate-100 text-xs px-2.5 py-1.5 focus:border-indigo-500 focus:ring-indigo-500 outline-none">
        <button type="button" id="cex-cover-manual-btn"
            class="flex-shrink-0 text-xs font-medium text-indigo-400 hover:text-indigo-300 px-2">Buscar</button>
    </div>
</div>

<!-- Datos básicos -->
<div class="pt-6 border-t border-slate-800 space-y-4">
    <h2 class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Datos básicos</h2>

    <div>
        <label for="title" class="{{ $label }}">Título</label>
        <input type="text" name="title" id="title" value="{{ old('title', $game?->title ?? $prefill['title'] ?? null) }}" required autofocus autocomplete="off" autocorrect="off" spellcheck="false" class="{{ $input }}">
        @error('title') <span class="{{ $error }}">{{ $message }}</span> @enderror
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <label for="ean" class="{{ $label }}">EAN</label>
            <input type="text" name="ean" id="ean" value="{{ old('ean', $game?->ean ?? $prefill['ean'] ?? null) }}" autocomplete="off" autocorrect="off" spellcheck="false" class="{{ $input }}">
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
            <input type="text" name="developer" id="developer" value="{{ old('developer', $game?->developer) }}" autocomplete="off" autocorrect="off" spellcheck="false" class="{{ $input }}">
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
                            {{ old('edition_id', $defaultEditionId) == $edition->id ? 'selected' : '' }}>
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
                        <input type="text" id="quick-edition-name" placeholder="Edición Coleccionista" autocomplete="off" autocorrect="off" spellcheck="false" class="{{ $input }}">
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
            value="{{ old('genres', $game?->genres ? implode(', ', $game->genres) : '') }}" autocomplete="off" autocorrect="off" spellcheck="false" class="{{ $input }}">
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
                <option value="owned" {{ old('status', $defaultStatus) == 'owned' ? 'selected' : '' }}>En colección</option>
                <option value="wishlist" {{ old('status', $defaultStatus) == 'wishlist' ? 'selected' : '' }}>Lista de deseos</option>
            </select>
            <!-- "Vendido" ya no se elige aquí: requiere precio y fecha de venta,
                 se marca desde el botón "Marcar como vendido" de la ficha de
                 detalle (games/show.blade.php), que archiva el juego en /sales. -->
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

    <div class="flex items-center gap-2">
        <input type="checkbox" name="for_sale" id="for_sale" value="1" {{ old('for_sale', $game?->for_sale) ? 'checked' : '' }}
            class="w-4 h-4 rounded border-slate-600 bg-slate-800 text-indigo-600 focus:ring-indigo-500">
        <label for="for_sale" class="text-sm text-slate-300">En venta</label>
        @error('for_sale') <span class="{{ $error }}">{{ $message }}</span> @enderror
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

<!-- Lista de deseos: solo tiene sentido mientras el juego no se ha comprado
     todavía (Propiedad = "Lista de deseos"), pero se deja siempre visible en
     vez de ocultarla con JS según el desplegable de arriba, igual que la
     sección "Compra" tampoco se oculta cuando el juego SÍ está en colección. -->
<div class="pt-6 border-t border-slate-800 space-y-4">
    <h2 class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Lista de deseos</h2>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div>
            <label for="wishlist_priority" class="{{ $label }}">Prioridad</label>
            <select name="wishlist_priority" id="wishlist_priority" class="{{ $input }}">
                <option value="">—</option>
                <option value="1" {{ (string) old('wishlist_priority', $game?->wishlist_priority) === '1' ? 'selected' : '' }}>Alta</option>
                <option value="2" {{ (string) old('wishlist_priority', $game?->wishlist_priority) === '2' ? 'selected' : '' }}>Media</option>
                <option value="3" {{ (string) old('wishlist_priority', $game?->wishlist_priority) === '3' ? 'selected' : '' }}>Baja</option>
            </select>
            @error('wishlist_priority') <span class="{{ $error }}">{{ $message }}</span> @enderror
        </div>
        <div>
            <label for="wishlist_estimated_price" class="{{ $label }}">Precio estimado</label>
            <input type="number" step="0.01" min="0" name="wishlist_estimated_price" id="wishlist_estimated_price"
                value="{{ old('wishlist_estimated_price', $game?->wishlist_estimated_price) }}" class="{{ $input }}">
            @error('wishlist_estimated_price') <span class="{{ $error }}">{{ $message }}</span> @enderror
        </div>
        <div>
            <label for="wishlist_store" class="{{ $label }}">Dónde comprarlo</label>
            <input type="text" name="wishlist_store" id="wishlist_store" value="{{ old('wishlist_store', $game?->wishlist_store) }}" autocomplete="off" autocorrect="off" spellcheck="false" class="{{ $input }}">
            @error('wishlist_store') <span class="{{ $error }}">{{ $message }}</span> @enderror
        </div>
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
            <input type="text" name="purchase_place" id="purchase_place" value="{{ old('purchase_place', $game?->purchase_place) }}" autocomplete="off" autocorrect="off" spellcheck="false" class="{{ $input }}">
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
            <select name="region_select" id="region_select" class="{{ $input }}">
                <option value="" {{ $regionSelectValue === '' ? 'selected' : '' }}>Sin especificar</option>
                @foreach($regionPresets as $preset)
                    <option value="{{ $preset }}" {{ $regionSelectValue === $preset ? 'selected' : '' }}>{{ $preset }}</option>
                @endforeach
                <option value="other" {{ $regionSelectValue === 'other' ? 'selected' : '' }}>Otra…</option>
            </select>
            <input type="text" name="region_other" id="region_other" maxlength="50" placeholder="Especifica la región"
                value="{{ old('region_other', $isCustomRegion ? $currentRegionSelect : '') }}"
                autocomplete="off" autocorrect="off" spellcheck="false"
                class="{{ $input }} mt-2 {{ $regionSelectValue === 'other' ? '' : 'hidden' }}">
            @error('region_select') <span class="{{ $error }}">{{ $message }}</span> @enderror
            @error('region_other') <span class="{{ $error }}">{{ $message }}</span> @enderror
        </div>
        <div>
            <label for="age_rating" class="{{ $label }}">Clasificación por edad</label>
            <input type="text" name="age_rating" id="age_rating" placeholder="PEGI 12..." value="{{ old('age_rating', $game?->age_rating) }}" autocomplete="off" autocorrect="off" spellcheck="false" class="{{ $input }}">
            @error('age_rating') <span class="{{ $error }}">{{ $message }}</span> @enderror
        </div>
    </div>
</div>

<!-- Notas -->
<div class="pt-6 border-t border-slate-800">
    <label for="notes" class="{{ $label }}">Notas</label>
    <textarea name="notes" id="notes" rows="3" autocomplete="off" autocorrect="off" spellcheck="false" class="{{ $input }}">{{ old('notes', $game?->notes) }}</textarea>
    @error('notes') <span class="{{ $error }}">{{ $message }}</span> @enderror
</div>

<script nonce="{{ $cspNonce }}">
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

    document.getElementById('region_select')?.addEventListener('change', toggleCustomRegion);

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

    /**
     * "Buscar carátula en CEX": busca este juego (guardado o todavía en
     * alta) en el catálogo externo y deja elegir una carátula entre los
     * resultados. No se descarga hasta guardar el formulario (mismo
     * mecanismo que la carátula sugerida desde la búsqueda rápida): aquí
     * solo se rellena el campo oculto cover_url y se actualiza la vista
     * previa. Al elegir un resultado también se rellena el campo EAN
     * (visible, editable, no se guarda hasta enviar el formulario como el
     * resto de campos).
     *
     * Por defecto busca por el EAN/título ya tecleados en el propio
     * formulario (funciona igual en alta que en edición: no depende de que
     * el juego ya esté guardado), pero si no da resultados o el usuario
     * quiere afinar, se puede repetir la búsqueda a mano con otras palabras
     * (input "Buscar con otras palabras").
     */
    (function () {
        const btn = document.getElementById('cex-cover-lookup-btn');
        if (!btn) return;

        const resultsEl = document.getElementById('cex-cover-results');
        const statusEl = document.getElementById('cex-cover-status');
        const manualWrapper = document.getElementById('cex-cover-manual');
        const manualInput = document.getElementById('cex-cover-manual-input');
        const manualBtn = document.getElementById('cex-cover-manual-btn');
        const coverUrlInput = document.getElementById('cover_url_input');
        const coverFileInput = document.getElementById('cover');
        const removeCoverCheckbox = document.querySelector('input[name="remove_cover"]');
        const eanInput = document.getElementById('ean');
        const titleInput = document.getElementById('title');

        async function search(query) {
            btn.disabled = true;
            manualBtn.disabled = true;
            resultsEl.classList.add('hidden');
            resultsEl.innerHTML = '';
            statusEl.classList.remove('hidden');
            statusEl.textContent = 'Buscando en CEX…';

            try {
                const url = query ? `${btn.dataset.url}?q=${encodeURIComponent(query)}` : btn.dataset.url;
                const response = await fetch(url, { headers: { 'Accept': 'application/json' } });
                if (!response.ok) throw new Error('request failed');
                const { results } = await response.json();

                if (!results.length) {
                    statusEl.textContent = 'Sin resultados en CEX. Prueba con otras palabras del título:';
                    manualWrapper.classList.remove('hidden');
                    if (!manualInput.value) manualInput.value = query || titleInput.value;
                    return;
                }

                statusEl.classList.add('hidden');
                manualWrapper.classList.remove('hidden');
                resultsEl.innerHTML = results.map((r, i) => `
                    <li>
                        <button type="button" class="js-cex-cover-pick w-full flex items-center gap-2 p-1.5 rounded-lg hover:bg-slate-700 text-left" data-index="${i}">
                            ${r.cover_url
                                ? `<img src="${r.cover_url}" alt="" class="w-8 h-8 object-cover rounded border border-slate-700 flex-shrink-0">`
                                : `<div class="w-8 h-8 rounded bg-slate-800 border border-slate-700 flex-shrink-0"></div>`}
                            <span class="flex-1 min-w-0">
                                <span class="block text-xs text-slate-200 truncate">${r.title}</span>
                                ${r.ean ? `<span class="block text-[10px] text-slate-500">EAN ${r.ean}</span>` : ''}
                            </span>
                            ${r.platform ? `<span class="text-[9px] font-semibold uppercase text-indigo-400 bg-indigo-500/10 border border-indigo-500/20 rounded px-1 py-0.5 flex-shrink-0">${r.platform}</span>` : ''}
                        </button>
                    </li>
                `).join('');
                resultsEl.classList.remove('hidden');

                resultsEl.querySelectorAll('.js-cex-cover-pick').forEach((el) => {
                    el.addEventListener('click', () => {
                        const result = results[Number(el.dataset.index)];

                        if (result.cover_url) {
                            document.getElementById('cover-wrapper').innerHTML =
                                `<img id="cover-preview-img" src="${result.cover_url}" alt="Carátula" class="w-24 h-auto rounded-xl border border-slate-700">`;
                            coverUrlInput.value = result.cover_url;
                            if (coverFileInput) coverFileInput.value = '';
                            if (removeCoverCheckbox) removeCoverCheckbox.checked = false;
                        }

                        if (result.ean && eanInput) {
                            eanInput.value = result.ean;
                        }

                        resultsEl.classList.add('hidden');
                        manualWrapper.classList.add('hidden');
                    });
                });
            } catch (err) {
                statusEl.classList.remove('hidden');
                statusEl.textContent = 'No se pudo buscar en CEX. Comprueba tu conexión e inténtalo de nuevo.';
            } finally {
                btn.disabled = false;
                manualBtn.disabled = false;
            }
        }

        btn.addEventListener('click', () => search(eanInput.value.trim() || titleInput.value.trim()));

        manualBtn.addEventListener('click', () => {
            const query = manualInput.value.trim();
            if (query) search(query);
        });

        manualInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                manualBtn.click();
            }
        });
    })();

    filterEditions();

    /**
     * Evita el reenvío duplicado del formulario en móvil: al volver atrás con
     * el gesto justo después de guardar, el navegador puede restaurar esta
     * página desde el bfcache (caché de navegación atrás/adelante) con el
     * botón de enviar todavía habilitado y los datos rellenos, y un segundo
     * toque lo reenvía sin querer. Deshabilitarlo al enviar no interrumpe el
     * envío en curso (ya lanzado por el propio submit), y el listener de
     * pageshow cubre el caso de que el bfcache no conserve ese estado
     * deshabilitado entre navegadores.
     */
    (function () {
        const form = document.currentScript.closest('form');
        const submitBtn = form?.querySelector('button[type="submit"]');
        if (!form || !submitBtn) return;

        form.addEventListener('submit', () => {
            submitBtn.disabled = true;
        });

        window.addEventListener('pageshow', (e) => {
            if (e.persisted) submitBtn.disabled = true;
        });
    })();
</script>
