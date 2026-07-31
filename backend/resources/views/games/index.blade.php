@extends('layouts.app')

@php
    $activeFilters = array_filter([
        !empty($query),
        $platformId !== '',
        $playStatus !== '',
        $status !== '',
    ]);
    $hasActiveFilters = count($activeFilters) > 0;
    $activeFilterCount = count($activeFilters);
@endphp

@section('content')
    <div class="mb-8 flex flex-col sm:flex-row sm:justify-between sm:items-end gap-4">
        <div>
            <h1 class="text-3xl font-bold text-slate-100 tracking-tight">Mi Colección</h1>
            <p class="text-slate-400 mt-1">Tienes {{ $games->total() }} juegos registrados.</p>
        </div>

        <div class="flex gap-2">
            <a href="{{ route('web.games.create') }}" class="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-indigo-500 transition-colors">
                + Añadir Juego
            </a>
        </div>
    </div>

    <!-- Buscador y filtros: colapsados tras un botón en móvil para no comerse la pantalla -->
    <details class="group md:hidden bg-slate-900 border border-slate-800 rounded-xl mb-6" {{ $hasActiveFilters ? 'open' : '' }}>
        <summary class="flex items-center justify-between gap-2 px-4 py-3 cursor-pointer select-none">
            <span class="flex items-center gap-2 text-sm font-medium text-slate-200">
                <x-gicon name="filter_list" class="text-[18px] text-slate-400" />
                Buscar y filtrar
                @if($hasActiveFilters)
                    <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-indigo-500 text-white text-[11px] font-bold">{{ $activeFilterCount }}</span>
                @endif
            </span>
            <x-gicon name="expand_more" class="text-[20px] text-slate-500 transition-transform duration-200 group-open:rotate-180" />
        </summary>

        <div class="px-4 pb-4">
            @include('games._filters')
        </div>
    </details>

    <div class="hidden md:block bg-slate-900 border border-slate-800 rounded-xl p-4 mb-6">
        @include('games._filters')
    </div>

    <!-- Tarjetas: listado en pantallas estrechas, sin scroll horizontal -->
    <div class="md:hidden space-y-2.5">
        @forelse($games as $game)
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-3 flex items-center gap-3">
                <x-game-cover :game="$game" size="lg" class="!w-14 !h-14 !rounded-xl !text-lg" />

                <div class="flex-1 min-w-0">
                    <h3 class="text-[15px] font-bold text-slate-100 truncate leading-snug">{{ $game->title }}</h3>

                    <div class="mt-1 flex items-center gap-1.5 flex-wrap">
                        <x-platform-chip :platform="$game->platform" class="!px-2 !py-0.5 !text-[10px]" />

                        <span class="inline-flex items-center gap-1 text-[11px] font-medium {{ $game->play_status === 'finished' ? 'text-emerald-400' : 'text-slate-500' }}">
                            @if($game->play_status === 'finished')
                                <x-gicon name="check_circle" class="text-[13px]" />
                            @else
                                <x-gicon name="schedule" class="text-[13px]" />
                            @endif
                            {{ $game->play_status ? ucfirst($game->play_status) : 'Pendiente' }}
                        </span>
                    </div>

                    <div class="mt-1.5 flex items-center gap-2">
                        <x-star-rating :rating="$game->rating" size="text-[11px]" />
                        @if($game->price_paid !== null)
                            <span class="text-[11px] font-semibold text-slate-400 tabular-nums">{{ number_format($game->price_paid, 2, ',', '.') }} €</span>
                        @endif
                    </div>
                </div>

                <div class="flex flex-col items-center gap-1 flex-shrink-0">
                    <a href="{{ route('web.games.edit', $game->id) }}"
                        class="flex items-center justify-center w-8 h-8 rounded-full text-slate-400 hover:bg-slate-800 hover:text-indigo-400 active:bg-slate-800 transition-colors"
                        aria-label="Editar {{ $game->title }}">
                        <x-gicon name="edit" class="text-[18px]" />
                    </a>

                    <form action="{{ route('web.games.destroy', $game->id) }}" method="POST" onsubmit="return confirm('¿Seguro que quieres enviar este juego a la papelera?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                            class="flex items-center justify-center w-8 h-8 rounded-full text-slate-400 hover:bg-slate-800 hover:text-red-400 active:bg-slate-800 transition-colors"
                            aria-label="Borrar {{ $game->title }}">
                            <x-gicon name="delete" class="text-[18px]" />
                        </button>
                    </form>
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
                    <th scope="col" class="px-6 py-3.5 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Título</th>
                    <th scope="col" class="px-6 py-3.5 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Plataforma</th>
                    <th scope="col" class="px-6 py-3.5 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Edición</th>
                    <th scope="col" class="px-6 py-3.5 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Estado</th>
                    <th scope="col" class="px-6 py-3.5 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Región</th>
                    <th scope="col" class="px-6 py-3.5 text-center text-xs font-semibold text-slate-400 uppercase tracking-wider">Manual</th>
                    <th scope="col" class="px-6 py-3.5 text-center text-xs font-semibold text-slate-400 uppercase tracking-wider">Valoración</th>
                    <th scope="col" class="px-6 py-3.5 text-right text-xs font-semibold text-slate-400 uppercase tracking-wider">Precio</th>
                    <th scope="col" class="px-6 py-3.5 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Fecha compra</th>
                    <th scope="col" class="px-6 py-3.5 text-right text-xs font-semibold text-slate-400 uppercase tracking-wider">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800">
                @forelse($games as $game)
                    <tr class="hover:bg-slate-800/40 transition-colors">
                        <!-- Título -->
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center gap-3">
                                <x-game-cover :game="$game" size="sm" />
                                <div class="text-sm font-bold text-slate-100">{{ $game->title }}</div>
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

                        <!-- Estado -->
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center gap-1.5 {{ $game->play_status === 'finished' ? 'text-emerald-400' : 'text-slate-400' }}">
                                @if($game->play_status === 'finished')
                                    <x-gicon name="check_circle" class="text-[20px]" />
                                @else
                                    <x-gicon name="schedule" class="text-[20px]" />
                                @endif
                                <span class="text-sm font-medium capitalize">
                                    {{ $game->play_status ?? 'Pendiente' }}
                                </span>
                            </div>
                        </td>

                        <!-- Región -->
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-300">
                            {{ $game->region ?? '—' }}
                        </td>

                        <!-- Manual -->
                        <td class="px-6 py-4 whitespace-nowrap text-center">
                            @if($game->manual_status === 'included')
                                <x-gicon name="check_circle" class="text-[18px] text-emerald-400" />
                            @elseif($game->manual_status === 'missing')
                                <x-gicon name="cancel" class="text-[18px] text-slate-600" />
                            @else
                                <span class="text-sm text-slate-500">—</span>
                            @endif
                        </td>

                        <!-- Valoración -->
                        <td class="px-6 py-4 whitespace-nowrap text-center">
                            <x-star-rating :rating="$game->rating" class="justify-center" />
                        </td>

                        <!-- Precio -->
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-slate-300">
                            {{ $game->price_paid !== null ? number_format($game->price_paid, 2, ',', '.') . ' €' : '—' }}
                        </td>

                        <!-- Fecha de compra -->
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-300">
                            {{ $game->purchase_date?->format('d/m/Y') ?? '—' }}
                        </td>

                        <!-- Acciones (Editar y Borrar) -->
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <div class="flex items-center justify-end gap-3">
                                <a href="{{ route('web.games.edit', $game->id) }}" class="text-indigo-400 hover:text-indigo-300 transition-colors">
                                    Editar
                                </a>

                                <form action="{{ route('web.games.destroy', $game->id) }}" method="POST" class="inline" onsubmit="return confirm('¿Seguro que quieres enviar este juego a la papelera?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-400 hover:text-red-300 transition-colors">
                                        Borrar
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="px-6 py-12 text-center text-slate-500 text-sm">
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

    @if($games->hasPages())
        <div class="mt-6">
            {{ $games->links() }}
        </div>
    @endif
@endsection
