@extends('layouts.app')

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

    <!-- Buscador (por título o EAN) y filtros -->
    <div class="bg-slate-900 border border-slate-800 rounded-xl p-4 mb-6">
        <form action="{{ route('web.games.index') }}" method="GET" class="flex flex-wrap gap-3">
            <input type="text" name="q" value="{{ $query ?? '' }}" placeholder="Buscar por título o EAN..."
                class="flex-1 min-w-[200px] rounded-lg border border-slate-700 bg-slate-800 text-slate-100 placeholder:text-slate-500 px-4 py-2.5 focus:border-indigo-500 focus:ring-indigo-500 outline-none text-sm">

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
                <option value="owned" {{ $status === 'owned' ? 'selected' : '' }}>En posesión</option>
                <option value="wishlist" {{ $status === 'wishlist' ? 'selected' : '' }}>Lista de deseos</option>
                <option value="sold" {{ $status === 'sold' ? 'selected' : '' }}>Vendido</option>
            </select>

            <button type="submit" class="bg-slate-700 text-slate-100 px-6 py-2.5 rounded-lg text-sm font-medium hover:bg-slate-600 transition-colors whitespace-nowrap">
                Filtrar
            </button>
            @if(!empty($query) || $platformId !== '' || $playStatus !== '' || $status !== '')
                <a href="{{ route('web.games.index') }}" class="flex items-center text-sm font-medium text-slate-400 hover:text-slate-100 whitespace-nowrap">
                    Limpiar
                </a>
            @endif
        </form>
    </div>

    <!-- Tarjetas: listado en pantallas estrechas, sin scroll horizontal -->
    <div class="md:hidden space-y-3">
        @forelse($games as $game)
            <div class="bg-slate-900 border border-slate-800 rounded-xl p-4">
                <div class="flex items-start gap-3">
                    <x-game-cover :game="$game" size="sm" />

                    <div class="flex-1 min-w-0">
                        <div class="flex items-start justify-between gap-2">
                            <h3 class="text-sm font-bold text-slate-100 truncate">{{ $game->title }}</h3>
                            <x-star-rating :rating="$game->rating" class="flex-shrink-0" />
                        </div>

                        <div class="mt-1.5">
                            <x-platform-chip :platform="$game->platform" />
                        </div>

                        <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-400">
                            <span class="flex items-center gap-1 {{ $game->play_status === 'finished' ? 'text-emerald-400' : '' }}">
                                @if($game->play_status === 'finished')
                                    <x-gicon name="check_circle" class="text-[16px]" />
                                @else
                                    <x-gicon name="schedule" class="text-[16px]" />
                                @endif
                                <span class="capitalize">{{ $game->play_status ?? 'Pendiente' }}</span>
                            </span>

                            @if($game->edition)
                                <span>{{ $game->edition->name }}</span>
                            @endif

                            @if($game->region)
                                <span>{{ $game->region }}</span>
                            @endif

                            @if($game->manual_status === 'included')
                                <span class="flex items-center gap-1 text-emerald-400">
                                    <x-gicon name="check_circle" class="text-[14px]" /> Manual
                                </span>
                            @endif

                            @if($game->price_paid !== null)
                                <span>{{ number_format($game->price_paid, 2, ',', '.') }} €</span>
                            @endif

                            @if($game->purchase_date)
                                <span>{{ $game->purchase_date->format('d/m/Y') }}</span>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="mt-3 pt-3 border-t border-slate-800 flex items-center justify-end gap-4 text-sm font-medium">
                    <a href="{{ route('web.games.edit', $game->id) }}" class="text-indigo-400 hover:text-indigo-300 transition-colors">
                        Editar
                    </a>

                    <form action="{{ route('web.games.destroy', $game->id) }}" method="POST" onsubmit="return confirm('¿Seguro que quieres enviar este juego a la papelera?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-red-400 hover:text-red-300 transition-colors">
                            Borrar
                        </button>
                    </form>
                </div>
            </div>
        @empty
            <div class="bg-slate-900 border border-slate-800 rounded-xl px-6 py-12 text-center text-slate-500 text-sm">
                @if(!empty($query) || $platformId !== '' || $playStatus !== '' || $status !== '')
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
                            @if(!empty($query) || $platformId !== '' || $playStatus !== '' || $status !== '')
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
