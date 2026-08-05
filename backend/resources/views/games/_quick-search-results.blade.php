@if($query === '')
    <p class="px-4 py-10 text-center text-sm text-slate-500">Escribe para buscar un juego por título o EAN.</p>
@elseif($games->isEmpty())
    <p class="px-4 py-10 text-center text-sm text-slate-500">Sin resultados para «{{ $query }}».</p>
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
                            @if($game->price_paid !== null)
                                <span class="text-xs text-emerald-400 tabular-nums">{{ number_format($game->price_paid, 2, ',', '.') }} €</span>
                            @endif
                        </div>
                    </div>

                    <x-star-rating :rating="$game->rating" size="text-[12px]" class="flex-shrink-0" />
                </a>
            </li>
        @endforeach
    </ul>
@endif
