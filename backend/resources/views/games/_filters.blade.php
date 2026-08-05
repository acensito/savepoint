<form action="{{ route('web.games.index') }}" method="GET">
    <!-- A todo el ancho del panel que lo envuelve: es el control principal de la
         página, así que ocupa el espacio en vez de quedarse pequeño en una esquina. -->
    <div class="flex gap-2">
        <input type="text" name="q" value="{{ $query ?? '' }}" placeholder="Buscar por título o EAN... (/)"
            class="js-game-search js-live-search flex-1 min-w-0 rounded-lg border border-slate-700 bg-slate-800 text-slate-100 placeholder:text-slate-500 px-4 py-3 focus:border-indigo-500 focus:ring-indigo-500 outline-none text-base">

        <button type="button" class="js-advanced-toggle relative flex-shrink-0 inline-flex items-center justify-center px-4 py-3 rounded-lg text-slate-400 hover:text-slate-100 hover:bg-slate-800 border border-slate-700 transition-colors"
            aria-label="Filtros avanzados" title="Filtros avanzados">
            <x-gicon name="tune" class="text-[18px]" />
            @if($hasAdvancedFilters)
                <span class="absolute top-1.5 right-1.5 w-1.5 h-1.5 rounded-full bg-indigo-500"></span>
            @endif
        </button>
    </div>

    <!-- Filtros avanzados: plegados por defecto (se despliegan con el botón de
         arriba, ver initAdvancedFiltersToggle en app.js); el buscador simple ya
         filtra solo con lo escrito, esto es para plataforma/estado/orden/paginación. -->
    <div class="js-advanced-panel {{ $hasAdvancedFilters ? '' : 'hidden' }} flex flex-wrap gap-3 mt-3 pt-3 border-t border-slate-800">
        <select name="platform_id" class="rounded-lg border border-slate-700 bg-slate-800 text-slate-100 px-4 py-2.5 focus:border-indigo-500 focus:ring-indigo-500 outline-none text-sm">
            <option value="">Todas las plataformas</option>
            @foreach($platforms as $platform)
                <option value="{{ $platform->id }}" {{ (string) $platformId === (string) $platform->id ? 'selected' : '' }}>
                    {{ $platform->name }}
                </option>
            @endforeach
        </select>

        <select name="play_status" class="rounded-lg border border-slate-700 bg-slate-800 text-slate-100 px-4 py-2.5 focus:border-indigo-500 focus:ring-indigo-500 outline-none text-sm">
            <option value="">Cualquier estado de juego</option>
            <option value="pending" {{ $playStatus === 'pending' ? 'selected' : '' }}>Pendiente</option>
            <option value="playing" {{ $playStatus === 'playing' ? 'selected' : '' }}>Jugando</option>
            <option value="finished" {{ $playStatus === 'finished' ? 'selected' : '' }}>Terminado</option>
        </select>

        <select name="status" class="rounded-lg border border-slate-700 bg-slate-800 text-slate-100 px-4 py-2.5 focus:border-indigo-500 focus:ring-indigo-500 outline-none text-sm">
            <option value="">Cualquier propiedad</option>
            <option value="owned" {{ $status === 'owned' ? 'selected' : '' }}>En colección</option>
            <option value="sold" {{ $status === 'sold' ? 'selected' : '' }}>Vendido</option>
        </select>

        <select name="sort" class="rounded-lg border border-slate-700 bg-slate-800 text-slate-100 px-4 py-2.5 focus:border-indigo-500 focus:ring-indigo-500 outline-none text-sm">
            <option value="">Más recientes primero</option>
            <option value="title" {{ $sort === 'title' ? 'selected' : '' }}>Título</option>
            <option value="price_paid" {{ $sort === 'price_paid' ? 'selected' : '' }}>Precio</option>
            <option value="rating" {{ $sort === 'rating' ? 'selected' : '' }}>Conservación</option>
            <option value="purchase_date" {{ $sort === 'purchase_date' ? 'selected' : '' }}>Fecha de compra</option>
        </select>

        <select name="dir" class="rounded-lg border border-slate-700 bg-slate-800 text-slate-100 px-4 py-2.5 focus:border-indigo-500 focus:ring-indigo-500 outline-none text-sm">
            <option value="desc" {{ $dir === 'desc' ? 'selected' : '' }}>Descendente</option>
            <option value="asc" {{ $dir === 'asc' ? 'selected' : '' }}>Ascendente</option>
        </select>

        <select name="per_page" class="rounded-lg border border-slate-700 bg-slate-800 text-slate-100 px-4 py-2.5 focus:border-indigo-500 focus:ring-indigo-500 outline-none text-sm">
            <option value="10" {{ $perPage === 10 ? 'selected' : '' }}>10 por página</option>
            <option value="20" {{ $perPage === 20 ? 'selected' : '' }}>20 por página</option>
            <option value="50" {{ $perPage === 50 ? 'selected' : '' }}>50 por página</option>
            <option value="100" {{ $perPage === 100 ? 'selected' : '' }}>100 por página</option>
        </select>

        <button type="submit" class="bg-slate-700 text-slate-100 px-6 py-2.5 rounded-lg text-sm font-medium hover:bg-slate-600 transition-colors whitespace-nowrap">
            Filtrar
        </button>
        @if($hasActiveFilters)
            <a href="{{ route('web.games.index') }}" class="flex items-center text-sm font-medium text-slate-400 hover:text-slate-100 whitespace-nowrap">
                Limpiar
            </a>
        @endif
    </div>
</form>
