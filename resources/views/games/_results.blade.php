<!-- Resultado del listado (tarjetas/tabla + estantería + paginación): parcial
     reutilizable para poder devolverlo tal cual, tanto en la carga normal de
     la página (incluido con @@include dentro de #games-results en index.blade.php)
     como por AJAX cuando el buscador filtra en vivo (ver initGamesLiveSearch en
     app.js y la rama $request->ajax() en GameController). A propósito NO incluye
     el <div id="games-results"> en sí: ese contenedor vive en index.blade.php y
     nunca se destruye, solo se le reemplaza el innerHTML por este parcial en
     cada refresco, para que los listeners delegados sobre él sigan funcionando
     sin tener que volver a engancharlos. -->

<!-- Marcador oculto con datos que el JS necesita tras un refresco por AJAX
     (el contador de la cabecera vive fuera de este parcial). -->
<div id="games-results-meta" class="hidden" data-total="{{ $games->total() }}"></div>

<div id="view-list">
        <!-- Tarjetas: listado en pantallas estrechas, sin scroll horizontal -->
        <div class="md:hidden space-y-2.5">
            @forelse($games as $game)
                <div class="js-game-card bg-slate-900 border border-slate-800 rounded-2xl p-3.5 flex items-center gap-3 shadow-sm shadow-black/10 active:border-slate-700 transition-colors cursor-pointer"
                    data-href="{{ route('web.games.show', $game->id) }}">
                    <input type="checkbox" form="bulk-form" name="game_ids[]" value="{{ $game->id }}"
                        class="js-bulk-checkbox self-start mt-1 w-4 h-4 flex-shrink-0 rounded border-slate-600 bg-slate-800 text-indigo-600 focus:ring-indigo-500"
                        aria-label="Seleccionar {{ $game->title }}">

                    <x-game-cover :game="$game" size="lg" class="!w-16 !rounded-xl !text-xl shadow-sm shadow-black/20" />

                    <div class="flex-1 min-w-0">
                        <div class="flex items-start justify-between gap-2">
                            <a href="{{ route('web.games.show', $game->id) }}" class="min-w-0 text-[15px] font-bold text-slate-100 line-clamp-2 leading-snug">{{ $game->title }}</a>
                            <x-platform-chip :platform="$game->platform" class="!px-2 !py-0.5 !text-[10px] flex-shrink-0" />
                        </div>

                        <div class="mt-1.5">
                            <div class="js-quick-rating" data-game-id="{{ $game->id }}" data-rating="{{ $game->rating }}">
                                <x-star-rating :rating="$game->rating" size="text-[11px]" />
                            </div>
                        </div>

                        <div class="mt-2 flex items-center justify-between gap-2">
                            <span class="inline-flex items-center gap-1 text-[11px] text-slate-500">
                                <x-gicon name="event" class="text-[13px]" />
                                {{ $game->purchase_date?->format('d/m/Y') ?? '—' }}
                            </span>
                            @if($game->price_paid !== null)
                                <span class="text-[11px] font-semibold text-emerald-400 tabular-nums bg-emerald-500/10 px-1.5 py-0.5 rounded-md">{{ number_format($game->price_paid, 2, ',', '.') }} €</span>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="bg-slate-900 border border-slate-800 rounded-xl px-6 py-12 text-center text-slate-500 text-sm">
                    @if($hasActiveFilters)
                        No hay juegos que coincidan con la búsqueda o los filtros aplicados.
                    @else
                        No hay juegos registrados todavía.
                    @endif
                </div>
            @endforelse
        </div>

        <!-- Tabla: listado en pantallas medianas y grandes -->
        <div class="hidden md:block bg-slate-900 border border-slate-800 rounded-xl overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-800">
                <thead class="bg-slate-800/50">
                    <tr>
                        <th scope="col" class="js-bulk-select-col pl-6 pr-2 py-3.5 w-4">
                            <input type="checkbox" id="bulk-select-all"
                                class="w-4 h-4 rounded border-slate-600 bg-slate-800 text-indigo-600 focus:ring-indigo-500"
                                aria-label="Seleccionar todos los juegos de esta página">
                        </th>
                        <th scope="col" class="px-6 py-3.5 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Título</th>
                        <th scope="col" class="px-6 py-3.5 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Plataforma</th>
                        <th scope="col" class="px-6 py-3.5 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Edición</th>
                        <th scope="col" class="px-6 py-3.5 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Región</th>
                        <th scope="col" class="px-6 py-3.5 text-center text-xs font-semibold text-slate-400 uppercase tracking-wider">Manual</th>
                        <th scope="col" class="px-6 py-3.5 text-center text-xs font-semibold text-slate-400 uppercase tracking-wider">Conservación</th>
                        <th scope="col" class="px-6 py-3.5 text-right text-xs font-semibold text-slate-400 uppercase tracking-wider">Precio</th>
                        <th scope="col" class="px-6 py-3.5 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Fecha compra</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    @forelse($games as $game)
                        <tr class="hover:bg-slate-800/40 transition-colors">
                            <!-- Selección para acciones en bloque -->
                            <td class="js-bulk-select-col pl-6 pr-2 py-4">
                                <input type="checkbox" form="bulk-form" name="game_ids[]" value="{{ $game->id }}"
                                    class="js-bulk-checkbox w-4 h-4 rounded border-slate-600 bg-slate-800 text-indigo-600 focus:ring-indigo-500"
                                    aria-label="Seleccionar {{ $game->title }}">
                            </td>

                            <!-- Título -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center gap-3">
                                    <x-game-cover :game="$game" size="sm" />
                                    <a href="{{ route('web.games.show', $game->id) }}" class="text-sm font-bold text-slate-100 hover:text-indigo-300 transition-colors">{{ $game->title }}</a>
                                </div>
                            </td>

                            <!-- Plataforma -->
                            <td class="px-6 py-4 whitespace-nowrap">
                                <x-platform-chip :platform="$game->platform" />
                            </td>

                            <!-- Edición -->
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-300">
                                {{ $game->edition?->name ?? '—' }}
                            </td>

                            <!-- Región -->
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-300">
                                {{ $game->region ?? '—' }}
                            </td>

                            <!-- Manual -->
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                @if($game->manual_status === 'included')
                                    <x-gicon name="check_circle" class="text-[18px] text-emerald-400" title="Con Manual" />
                                @elseif($game->manual_status === 'booklet')
                                    <x-gicon name="description" class="text-[18px] text-emerald-400" title="Folleto" />
                                @elseif($game->manual_status === 'missing')
                                    <x-gicon name="warning" class="text-[18px] text-amber-400" title="Sin Manual" />
                                @else
                                    <span class="text-sm text-slate-500">—</span>
                                @endif
                            </td>

                            <!-- Conservación (solo lectura: se cambia desde el formulario de edición) -->
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <x-star-rating :rating="$game->rating" size="text-[10px]" class="justify-center" />
                            </td>

                            <!-- Precio -->
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-slate-300">
                                {{ $game->price_paid !== null ? number_format($game->price_paid, 2, ',', '.') . ' €' : '—' }}
                            </td>

                            <!-- Fecha de compra -->
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-300">
                                {{ $game->purchase_date?->format('d/m/Y') ?? '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-6 py-12 text-center text-slate-500 text-sm">
                                @if($hasActiveFilters)
                                    No hay juegos que coincidan con la búsqueda o los filtros aplicados.
                                @else
                                    No hay juegos registrados todavía.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Estantería: grid de carátulas grandes, alternativa visual a la tabla/tarjetas
         de arriba (oculta por defecto, ver #view-grid en app.css). -->
    <div id="view-grid" class="grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
        @forelse($games as $game)
            <div class="group relative">
                <input type="checkbox" form="bulk-form" name="game_ids[]" value="{{ $game->id }}"
                    class="js-bulk-checkbox absolute top-2 left-2 z-10 w-4 h-4 rounded border-slate-500 bg-slate-900/80 text-indigo-600 focus:ring-indigo-500"
                    aria-label="Seleccionar {{ $game->title }}">

                <a href="{{ route('web.games.show', $game->id) }}" class="block">
                    <x-game-cover :game="$game" size="lg" class="!w-full !aspect-[3/4] !h-auto !rounded-xl !text-3xl group-hover:opacity-80 transition-opacity" />
                </a>

                <div class="mt-2">
                    <a href="{{ route('web.games.show', $game->id) }}" class="block text-sm font-semibold text-slate-100 truncate hover:text-indigo-300 transition-colors">
                        {{ $game->title }}
                    </a>
                    <div class="mt-1 flex items-center justify-between gap-2">
                        <x-platform-chip :platform="$game->platform" class="!px-2 !py-0.5 !text-[10px]" />
                        <div class="js-quick-rating" data-game-id="{{ $game->id }}" data-rating="{{ $game->rating }}">
                            <x-star-rating :rating="$game->rating" size="text-[11px]" />
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-full bg-slate-900 border border-slate-800 rounded-xl px-6 py-12 text-center text-slate-500 text-sm">
                @if($hasActiveFilters)
                    No hay juegos que coincidan con la búsqueda o los filtros aplicados.
                @else
                    No hay juegos registrados todavía.
                @endif
            </div>
        @endforelse
    </div>

    @if($games->hasPages())
        <div class="mt-6">
            {{ $games->links() }}
        </div>
    @endif
