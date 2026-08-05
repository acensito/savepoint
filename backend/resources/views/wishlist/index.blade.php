@extends('layouts.app')

@section('content')
    @php
        $priorityLabels = [1 => 'Alta', 2 => 'Media', 3 => 'Baja'];
        $priorityClasses = [
            1 => 'bg-red-500/10 text-red-400 border-red-500/30',
            2 => 'bg-amber-500/10 text-amber-400 border-amber-500/30',
            3 => 'bg-slate-500/10 text-slate-400 border-slate-500/30',
        ];
    @endphp

    <div class="mb-8 flex flex-col sm:flex-row sm:justify-between sm:items-end gap-4">
        <div>
            <h1 class="text-3xl font-bold text-slate-100 tracking-tight">Lista de deseos</h1>
            <p class="text-slate-400 mt-1">{{ $games->total() }} {{ \Illuminate\Support\Str::plural('juego', $games->total()) }} que todavía no tienes.</p>
        </div>

        <a href="{{ route('web.wishlist.create') }}"
            class="inline-flex items-center justify-center gap-1.5 rounded-lg px-4 py-2 bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-500 transition-colors whitespace-nowrap">
            <x-gicon name="add" class="text-[18px]" />
            Añadir Juego
        </a>
    </div>

    <form action="{{ route('web.wishlist.index') }}" method="GET" class="flex flex-wrap gap-3 mb-6">
        <input type="text" name="q" value="{{ $query ?? '' }}" placeholder="Buscar por título o EAN..."
            class="flex-1 min-w-[200px] rounded-lg border border-slate-700 bg-slate-800 text-slate-100 placeholder:text-slate-500 px-4 py-2.5 focus:border-indigo-500 focus:ring-indigo-500 outline-none text-sm">

        <select name="sort" class="rounded-lg border border-slate-700 bg-slate-800 text-slate-100 px-4 py-2.5 focus:border-indigo-500 focus:ring-indigo-500 outline-none text-sm">
            <option value="wishlist_priority" {{ $sort === 'wishlist_priority' || $sort === '' ? 'selected' : '' }}>Prioridad</option>
            <option value="title" {{ $sort === 'title' ? 'selected' : '' }}>Título</option>
            <option value="wishlist_estimated_price" {{ $sort === 'wishlist_estimated_price' ? 'selected' : '' }}>Precio estimado</option>
        </select>

        <select name="dir" class="rounded-lg border border-slate-700 bg-slate-800 text-slate-100 px-4 py-2.5 focus:border-indigo-500 focus:ring-indigo-500 outline-none text-sm">
            <option value="asc" {{ $dir === 'asc' ? 'selected' : '' }}>Ascendente</option>
            <option value="desc" {{ $dir === 'desc' ? 'selected' : '' }}>Descendente</option>
        </select>

        <button type="submit" class="bg-slate-700 text-slate-100 px-6 py-2.5 rounded-lg text-sm font-medium hover:bg-slate-600 transition-colors whitespace-nowrap">
            Filtrar
        </button>
        @if(($query ?? '') !== '')
            <a href="{{ route('web.wishlist.index') }}" class="flex items-center text-sm font-medium text-slate-400 hover:text-slate-100 whitespace-nowrap">
                Limpiar
            </a>
        @endif
    </form>

    <!-- Tarjetas: listado en pantallas estrechas, sin scroll horizontal -->
    <div class="md:hidden space-y-2.5">
        @forelse($games as $game)
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-3.5 flex items-start gap-3 shadow-sm shadow-black/10">
                <x-game-cover :game="$game" size="lg" class="!w-16 !rounded-xl !text-xl shadow-sm shadow-black/20" />

                <div class="flex-1 min-w-0">
                    <a href="{{ route('web.games.show', $game->id) }}" class="block text-[15px] font-bold text-slate-100 truncate leading-snug">{{ $game->title }}</a>

                    <div class="mt-1.5 flex items-center gap-1.5 flex-wrap">
                        <x-platform-chip :platform="$game->platform" class="!px-2 !py-0.5 !text-[10px]" />
                        @if($game->wishlist_priority)
                            <span class="inline-flex items-center text-[10px] font-medium rounded px-1.5 py-0.5 border {{ $priorityClasses[$game->wishlist_priority] }}">
                                {{ $priorityLabels[$game->wishlist_priority] }}
                            </span>
                        @endif
                    </div>

                    <div class="mt-2 flex items-center gap-2 flex-wrap text-[11px] text-slate-400">
                        @if($game->wishlist_estimated_price !== null)
                            <span class="font-semibold text-emerald-400 tabular-nums bg-emerald-500/10 px-1.5 py-0.5 rounded-md">~{{ number_format($game->wishlist_estimated_price, 2, ',', '.') }} €</span>
                        @endif
                        @if($game->wishlist_store)
                            <span class="truncate">{{ $game->wishlist_store }}</span>
                        @endif
                    </div>
                </div>

                <div class="flex flex-col items-center gap-1 flex-shrink-0 border-l border-slate-800 pl-2 -my-1 py-1">
                    <a href="{{ route('web.games.edit', ['game' => $game->id, 'convert_to_owned' => 1]) }}"
                        class="flex items-center justify-center w-8 h-8 rounded-full text-slate-400 hover:bg-slate-800 hover:text-emerald-400 transition-colors"
                        aria-label="Pasar «{{ $game->title }}» a la colección">
                        <x-gicon name="shopping_cart" class="text-[18px]" />
                    </a>
                    <a href="{{ route('web.games.edit', $game->id) }}"
                        class="flex items-center justify-center w-8 h-8 rounded-full text-slate-400 hover:bg-slate-800 hover:text-indigo-400 transition-colors"
                        aria-label="Editar {{ $game->title }}">
                        <x-gicon name="edit" class="text-[18px]" />
                    </a>
                    <form action="{{ route('web.games.destroy', $game->id) }}" method="POST" class="js-confirm-delete"
                        data-confirm-title="Enviar a la papelera"
                        data-confirm-message="«{{ $game->title }}» se moverá a la papelera. Podrás restaurarlo más tarde.">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                            class="flex items-center justify-center w-8 h-8 rounded-full text-slate-400 hover:bg-slate-800 hover:text-red-400 transition-colors"
                            aria-label="Borrar {{ $game->title }}">
                            <x-gicon name="delete" class="text-[18px]" />
                        </button>
                    </form>
                </div>
            </div>
        @empty
            <div class="bg-slate-900 border border-slate-800 rounded-xl px-6 py-12 text-center text-slate-500 text-sm">
                @if(($query ?? '') !== '')
                    No hay juegos en tu lista de deseos que coincidan con la búsqueda.
                @else
                    No tienes juegos en tu lista de deseos todavía.
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
                    <th scope="col" class="px-6 py-3.5 text-center text-xs font-semibold text-slate-400 uppercase tracking-wider">Prioridad</th>
                    <th scope="col" class="px-6 py-3.5 text-right text-xs font-semibold text-slate-400 uppercase tracking-wider">Precio estimado</th>
                    <th scope="col" class="px-6 py-3.5 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Dónde comprarlo</th>
                    <th scope="col" class="px-6 py-3.5 text-right text-xs font-semibold text-slate-400 uppercase tracking-wider">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800">
                @forelse($games as $game)
                    <tr class="hover:bg-slate-800/40 transition-colors">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-start gap-3">
                                <x-game-cover :game="$game" size="sm" />
                                <a href="{{ route('web.games.show', $game->id) }}" class="text-sm font-bold text-slate-100 hover:text-indigo-300 transition-colors pt-1.5">{{ $game->title }}</a>
                            </div>
                        </td>

                        <td class="px-6 py-4 whitespace-nowrap">
                            <x-platform-chip :platform="$game->platform" />
                        </td>

                        <td class="px-6 py-4 whitespace-nowrap text-center">
                            @if($game->wishlist_priority)
                                <span class="inline-flex items-center text-xs font-medium rounded px-2 py-0.5 border {{ $priorityClasses[$game->wishlist_priority] }}">
                                    {{ $priorityLabels[$game->wishlist_priority] }}
                                </span>
                            @else
                                <span class="text-sm text-slate-500">—</span>
                            @endif
                        </td>

                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-slate-300">
                            {{ $game->wishlist_estimated_price !== null ? number_format($game->wishlist_estimated_price, 2, ',', '.') . ' €' : '—' }}
                        </td>

                        <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-300">
                            {{ $game->wishlist_store ?? '—' }}
                        </td>

                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <div class="flex items-center justify-end gap-3">
                                <a href="{{ route('web.games.edit', ['game' => $game->id, 'convert_to_owned' => 1]) }}" class="text-emerald-400 hover:text-emerald-300 transition-colors">
                                    Pasar a la colección
                                </a>
                                <a href="{{ route('web.games.edit', $game->id) }}" class="text-slate-400 hover:text-slate-100 transition-colors">
                                    Editar
                                </a>
                                <form action="{{ route('web.games.destroy', $game->id) }}" method="POST" class="inline js-confirm-delete"
                                    data-confirm-title="Enviar a la papelera"
                                    data-confirm-message="«{{ $game->title }}» se moverá a la papelera. Podrás restaurarlo más tarde.">
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
                        <td colspan="6" class="px-6 py-12 text-center text-slate-500 text-sm">
                            @if(($query ?? '') !== '')
                                No hay juegos en tu lista de deseos que coincidan con la búsqueda.
                            @else
                                No tienes juegos en tu lista de deseos todavía.
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
