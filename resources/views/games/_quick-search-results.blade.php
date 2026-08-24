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
        <!-- Sugerencias de CEX: solo se consultan cuando no hay match local
             (ver SearchController::quick). Cada botón lleva sus datos en
             data-* para que la ficha de comprobación de abajo se pinte sin
             otra petición (ver initExternalResultPreview en app.js). -->
        <p class="px-4 pb-2 text-xs font-semibold text-slate-500 uppercase tracking-wider">Sugerencias de CEX</p>
        <ul id="cex-results-list" class="pb-2">
            @foreach($externalResults as $result)
                <li>
                    <button type="button" class="js-cex-result w-full flex items-center gap-3 px-4 py-2.5 hover:bg-slate-800 transition-colors text-left"
                        data-title="{{ $result->title }}" data-ean="{{ $result->ean }}" data-cover="{{ $result->coverUrl }}" data-platform="{{ $result->platform }}">
                        @if($result->coverUrl)
                            <img src="{{ $result->coverUrl }}" alt="" class="w-10 h-10 object-cover rounded-lg border border-slate-700 flex-shrink-0">
                        @else
                            <div class="w-10 h-10 rounded-lg bg-slate-800 border border-slate-700 flex-shrink-0"></div>
                        @endif
                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-medium text-slate-100 truncate">{{ $result->title }}</div>
                            <div class="flex items-center gap-2 mt-0.5">
                                @if($result->platform)
                                    <span class="text-[10px] font-semibold uppercase tracking-wide text-indigo-400 bg-indigo-500/10 border border-indigo-500/20 rounded px-1.5 py-0.5">{{ $result->platform }}</span>
                                @endif
                                @if($result->ean)
                                    <span class="text-xs text-slate-500">EAN {{ $result->ean }}</span>
                                @endif
                            </div>
                        </div>
                        <x-gicon name="chevron_right" class="text-[16px] text-slate-600 flex-shrink-0" />
                    </button>
                </li>
            @endforeach
        </ul>

        <!-- Ficha de comprobación: oculta hasta que se pulsa un resultado de
             arriba. Sus datos se rellenan por JS desde el data-* del botón
             pulsado, no desde otra petición al servidor. -->
        <div id="cex-preview" data-create-url="{{ route('web.games.create') }}" class="hidden px-4 py-4 border-t border-slate-800">
            <button type="button" class="js-cex-preview-back flex items-center gap-1 text-xs text-slate-500 hover:text-slate-300 mb-3">
                <x-gicon name="arrow_back" class="text-[14px]" />
                Volver a los resultados
            </button>
            <div class="flex items-start gap-4">
                <img id="cex-preview-cover" src="" alt="" class="hidden w-20 h-auto rounded-xl border border-slate-700 flex-shrink-0">
                <div class="flex-1 min-w-0">
                    <div id="cex-preview-title" class="text-base font-semibold text-slate-100"></div>
                    <div class="flex items-center gap-2 mt-1">
                        <span id="cex-preview-platform" class="hidden text-[10px] font-semibold uppercase tracking-wide text-indigo-400 bg-indigo-500/10 border border-indigo-500/20 rounded px-1.5 py-0.5"></span>
                        <div id="cex-preview-ean" class="text-sm text-slate-500"></div>
                    </div>
                    <p class="text-xs text-slate-500 mt-2">Comprueba que los datos coinciden antes de darlo de alta: la búsqueda es de CEX, no de tu colección.</p>
                </div>
            </div>
            <a id="cex-preview-add-link" href="#" class="mt-4 inline-flex items-center gap-1.5 bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-indigo-500 transition-colors">
                <x-gicon name="add_circle" class="text-[16px]" />
                Dar de alta
            </a>
        </div>
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

                    <x-star-rating :rating="$game->rating" size="text-[12px]" class="flex-shrink-0" />
                </a>
            </li>
        @endforeach
    </ul>

    <!-- Puente a la vista completa: el modal no pagina ni ordena, se limita a
         los primeros MAX_RESULTS (ver SearchController::quick). -->
    <div class="px-4 py-3 border-t border-slate-800 text-center">
        <a href="{{ $viewAllUrl }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-indigo-400 hover:text-indigo-300">
            Ver todos los resultados en la colección
            <x-gicon name="arrow_forward" class="text-[16px]" />
        </a>
    </div>
@endif
