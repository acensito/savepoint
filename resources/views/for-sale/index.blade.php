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
        <!-- Tarjetas: listado en pantallas estrechas, sin scroll horizontal -->
        <div class="md:hidden space-y-2.5">
            @foreach($games as $game)
                <div class="bg-slate-900 border border-slate-800 rounded-2xl p-3.5 flex items-center gap-3">
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

        <!-- Tabla: listado en pantallas medianas y grandes -->
        <div class="hidden md:block bg-slate-900 border border-slate-800 rounded-xl overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-800">
                <thead class="bg-slate-800/50">
                    <tr>
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
    @endif
@endsection
