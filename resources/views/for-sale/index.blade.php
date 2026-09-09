@extends('layouts.app')

@section('content')
    <div class="mb-8 flex flex-col sm:flex-row sm:justify-between sm:items-end gap-4">
        <div>
            <h1 class="text-3xl font-bold text-slate-100 tracking-tight">En venta</h1>
            <p class="text-slate-400 mt-1">
                {{ $games->count() }} {{ Str::plural('juego', $games->count()) }} marcados como en venta.
                @if(auth()->user()->hide_for_sale_from_collection)
                    Ocultos de tu colección principal (cambia esto en Ajustes).
                @endif
            </p>
        </div>

        <a href="{{ route('web.games.index') }}" class="text-sm font-medium text-slate-400 hover:text-slate-100">
            ← Volver a mi colección
        </a>
    </div>

    @if($games->isEmpty())
        <div class="bg-slate-900 border border-slate-800 rounded-xl px-6 py-12 text-center text-slate-500 text-sm">
            No tienes ningún juego marcado como en venta. Márcalo desde su ficha de detalle.
        </div>
    @else
        <!-- Formulario "fantasma" de las acciones en bloque, mismo patrón que
             games/index.blade.php: las casillas viven fuera de él (form="bulk-form"). -->
        <form id="bulk-form" method="POST">
            @csrf
        </form>

        <div id="bulk-bar" class="hidden flex items-center gap-3 flex-wrap bg-indigo-500/10 border border-indigo-500/30 rounded-xl px-4 py-3 mb-4">
            <span id="bulk-count" class="text-sm font-medium text-indigo-200 flex-1 min-w-[140px]">0 juegos seleccionados</span>

            <button type="submit" form="bulk-form" formaction="{{ route('web.games.bulk-unmark-for-sale') }}"
                class="bg-slate-700 text-slate-100 px-4 py-2 rounded-lg text-sm font-medium hover:bg-slate-600 transition-colors whitespace-nowrap">
                Quitar de en venta
            </button>
        </div>

        <!-- always-selectable (ver app.css): a diferencia de la colección
             principal, aquí las casillas van siempre visibles, sin "modo
             selección" que activar primero. -->
        <div id="games-results" class="always-selectable">
        <!-- Tarjetas: listado en pantallas estrechas, sin scroll horizontal.
             Breakpoint en xl, no md (issue #127): con md la tabla —pensada
             para escritorio con ratón— se veía también en tablet. -->
        <div class="xl:hidden space-y-2.5">
            @foreach($games as $game)
                <div class="bg-slate-900 border border-slate-800 rounded-2xl p-3.5 flex items-center gap-3">
                    <input type="checkbox" form="bulk-form" name="game_ids[]" value="{{ $game->id }}"
                        class="js-bulk-checkbox self-start mt-1 w-4 h-4 shrink-0 rounded border-slate-600 bg-slate-800 text-indigo-600 focus:ring-indigo-500">

                    <a href="{{ route('web.games.show', $game->id) }}" class="shrink-0">
                        <x-game-cover :game="$game" size="sm" />
                    </a>

                    <div class="flex-1 min-w-0">
                        <a href="{{ route('web.games.show', $game->id) }}" class="flex items-start justify-between gap-2">
                            <span class="min-w-0 text-[15px] font-bold text-slate-100 line-clamp-2 leading-snug">{{ $game->title }}</span>
                            <x-platform-chip :platform="$game->platform" class="!px-2 !py-0.5 !text-[10px] shrink-0" />
                        </a>

                        <div class="mt-1.5 flex items-center gap-2">
                            <x-star-rating :rating="$game->rating" size="text-[11px]" />
                            @if($game->price_paid !== null)
                                <span class="text-[12px] text-slate-400 tabular-nums">{{ number_format($game->price_paid, 2, ',', '.') }} €</span>
                            @endif
                        </div>

                        <div class="mt-2.5 flex items-center justify-between gap-3 text-sm font-medium">
                            <a href="{{ route('web.games.show', $game->id) }}#mark-sold-trigger" class="text-emerald-400 hover:text-emerald-300 transition-colors">
                                Marcar como vendido
                            </a>
                            <form action="{{ route('web.games.quick-update', $game->id) }}" method="POST">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="for_sale" value="0">
                                <button type="submit" class="text-slate-400 hover:text-slate-100 transition-colors">
                                    Quitar
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <!-- Tabla: listado en pantallas grandes (ver comentario del breakpoint arriba) -->
        <div class="hidden xl:block bg-slate-900 border border-slate-800 rounded-xl overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-800">
                <thead class="bg-slate-800/50">
                    <tr>
                        <th scope="col" class="js-bulk-select-col pl-6 pr-2 py-3.5 w-4">
                            <input type="checkbox" id="bulk-select-all"
                                class="w-4 h-4 rounded border-slate-600 bg-slate-800 text-indigo-600 focus:ring-indigo-500"
                                aria-label="Seleccionar todos">
                        </th>
                        <th scope="col" class="px-6 py-3.5 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Título</th>
                        <th scope="col" class="px-6 py-3.5 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Plataforma</th>
                        <th scope="col" class="px-6 py-3.5 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Edición</th>
                        <th scope="col" class="px-6 py-3.5 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Región</th>
                        <th scope="col" class="px-6 py-3.5 text-center text-xs font-semibold text-slate-400 uppercase tracking-wider">Conservación</th>
                        <th scope="col" class="px-6 py-3.5 text-right text-xs font-semibold text-slate-400 uppercase tracking-wider">Compra</th>
                        <th scope="col" class="px-6 py-3.5 text-right text-xs font-semibold text-slate-400 uppercase tracking-wider">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    @foreach($games as $game)
                        <tr class="hover:bg-slate-800/40 transition-colors">
                            <td class="js-bulk-select-col pl-6 pr-2 py-4">
                                <input type="checkbox" form="bulk-form" name="game_ids[]" value="{{ $game->id }}"
                                    class="js-bulk-checkbox w-4 h-4 rounded border-slate-600 bg-slate-800 text-indigo-600 focus:ring-indigo-500">
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-slate-100">
                                <a href="{{ route('web.games.show', $game->id) }}" class="hover:text-indigo-400 transition-colors">
                                    {{ $game->title }}
                                </a>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap"><x-platform-chip :platform="$game->platform" /></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-300">{{ $game->edition?->name ?? '—' }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-300">{{ $game->region ?? '—' }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <x-star-rating :rating="$game->rating" size="text-[10px]" class="justify-center" />
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-slate-300 tabular-nums">
                                {{ $game->price_paid !== null ? number_format($game->price_paid, 2, ',', '.') . ' €' : '—' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <div class="flex items-center justify-end gap-4">
                                    <a href="{{ route('web.games.show', $game->id) }}#mark-sold-trigger" class="text-emerald-400 hover:text-emerald-300 transition-colors">
                                        Marcar como vendido
                                    </a>
                                    <form action="{{ route('web.games.quick-update', $game->id) }}" method="POST">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="for_sale" value="0">
                                        <button type="submit" class="text-slate-400 hover:text-slate-100 transition-colors">
                                            Quitar de venta
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        </div>
    @endif
@endsection
