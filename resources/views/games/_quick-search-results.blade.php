@if($query === '' && !$hasFilters)
    <p class="px-4 py-10 text-center text-sm text-slate-500">Escribe para buscar un juego por título o EAN, o elige un filtro.</p>
@elseif($games->isEmpty() && $query === '')
    {{-- Solo hay filtros activos (plataforma/estado), sin texto: no hay título
         que ofrecer para dar de alta, así que no tiene sentido el CTA de CEX
         ni el de "dar de alta a mano" de más abajo. --}}
    <p class="px-4 py-10 text-center text-sm text-slate-500">Sin resultados con los filtros seleccionados.</p>
@elseif($games->isEmpty())
    <div class="px-4 py-4 text-center">
        <p class="text-sm text-slate-500">
            {{ $isEan ? 'Ningún juego con ese código de barras en tu colección.' : "Sin resultados para «{$query}»." }}
        </p>
    </div>

    @if(!empty($externalResults))
        <p class="px-4 pb-2 text-xs font-semibold text-slate-500 uppercase tracking-wider">Sugerencias de CEX</p>
        @include('games._quick-search-cex-results')
    @else
        {{-- Ni coincidencia local ni en CEX: en vez de dejar el hueco en
             blanco, se sugiere afinar la búsqueda antes del enlace de alta
             manual de más abajo (#108). --}}
        <p class="px-4 pb-4 text-center text-xs text-slate-500">Prueba a acortar el título o revisar cómo está escrito.</p>
    @endif

    <div class="px-4 py-3 {{ !empty($externalResults) ? 'border-t border-slate-800' : '' }} text-center">
        <a href="{{ route('web.games.create', $isEan ? ['ean' => $query] : ['title' => $query]) }}"
            class="inline-flex items-center gap-1.5 text-sm font-medium text-indigo-400 hover:text-indigo-300">
            <x-gicon name="add_circle" class="text-[16px]" />
            Dar de alta «{{ $query }}» a mano
        </a>
    </div>
@else
    <ul class="py-2">
        @foreach($games as $game)
            <li>
                <a href="{{ route('web.games.show', $game->id) }}"
                    class="flex items-center gap-3 px-4 py-2.5 hover:bg-slate-800 transition-colors">
                    <x-game-cover :game="$game" size="sm" />

                    <div class="flex-1 min-w-0">
                        <div class="text-sm font-medium text-slate-100 truncate">{{ $game->title }}</div>
                        <div class="flex items-center gap-2 mt-1">
                            <x-platform-chip :platform="$game->platform" class="!px-1.5 !py-0.5 !text-[10px]" />
                            @if($game->status === 'wishlist')
                                <span class="inline-flex items-center gap-0.5 text-[10px] font-medium text-red-400" title="En tu lista de deseos">
                                    <x-gicon name="favorite" :filled="true" class="text-[12px]" />
                                    En lista deseos
                                </span>
                            @elseif($game->price_paid !== null)
                                <span class="text-xs text-emerald-400 tabular-nums">{{ number_format($game->price_paid, 2, ',', '.') }} €</span>
                            @endif
                        </div>
                    </div>

                    <x-star-rating :rating="$game->rating" size="text-[12px]" class="shrink-0" />
                </a>
            </li>
        @endforeach
    </ul>

    @if(!empty($externalResults))
        {{-- Ya hay coincidencias en la colección (arriba): esto es solo un
             complemento por si se trata de una entrega nueva a añadir, ya
             recortado a un par y sin repetir títulos ya poseídos (ver
             SearchController::quick, #108). --}}
        <p class="px-4 pt-3 pb-2 text-xs font-semibold text-slate-500 uppercase tracking-wider border-t border-slate-800">¿Buscas otra edición? Sugerencias de CEX</p>
        @include('games._quick-search-cex-results')
    @endif

    <!-- Puente a la vista completa: el modal no pagina ni ordena, se limita a
         los primeros MAX_RESULTS (ver SearchController::quick). -->
    <div class="px-4 py-3 border-t border-slate-800 text-center">
        <a href="{{ $viewAllUrl }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-indigo-400 hover:text-indigo-300">
            Ver todos los resultados en la colección
            <x-gicon name="arrow_forward" class="text-[16px]" />
        </a>
    </div>
@endif
