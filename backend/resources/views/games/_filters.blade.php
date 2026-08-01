<form action="{{ route('web.games.index') }}" method="GET" class="flex flex-wrap gap-3">
    <input type="text" name="q" value="{{ $query ?? '' }}" placeholder="Buscar por título o EAN... (/)"
        class="js-game-search flex-1 min-w-[200px] rounded-lg border border-slate-700 bg-slate-800 text-slate-100 placeholder:text-slate-500 px-4 py-2.5 focus:border-indigo-500 focus:ring-indigo-500 outline-none text-sm">

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
        <option value="wishlist" {{ $status === 'wishlist' ? 'selected' : '' }}>Lista de deseos</option>
        <option value="sold" {{ $status === 'sold' ? 'selected' : '' }}>Vendido</option>
    </select>

    <select name="sort" class="rounded-lg border border-slate-700 bg-slate-800 text-slate-100 px-4 py-2.5 focus:border-indigo-500 focus:ring-indigo-500 outline-none text-sm">
        <option value="">Más recientes primero</option>
        <option value="title" {{ $sort === 'title' ? 'selected' : '' }}>Título</option>
        <option value="price_paid" {{ $sort === 'price_paid' ? 'selected' : '' }}>Precio</option>
        <option value="rating" {{ $sort === 'rating' ? 'selected' : '' }}>Valoración</option>
        <option value="purchase_date" {{ $sort === 'purchase_date' ? 'selected' : '' }}>Fecha de compra</option>
    </select>

    <select name="dir" class="rounded-lg border border-slate-700 bg-slate-800 text-slate-100 px-4 py-2.5 focus:border-indigo-500 focus:ring-indigo-500 outline-none text-sm">
        <option value="desc" {{ $dir === 'desc' ? 'selected' : '' }}>Descendente</option>
        <option value="asc" {{ $dir === 'asc' ? 'selected' : '' }}>Ascendente</option>
    </select>

    <button type="submit" class="bg-slate-700 text-slate-100 px-6 py-2.5 rounded-lg text-sm font-medium hover:bg-slate-600 transition-colors whitespace-nowrap">
        Filtrar
    </button>
    @if($hasActiveFilters)
        <a href="{{ route('web.games.index') }}" class="flex items-center text-sm font-medium text-slate-400 hover:text-slate-100 whitespace-nowrap">
            Limpiar
        </a>
    @endif
</form>
