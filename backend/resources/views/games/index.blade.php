@extends('layouts.app')

@section('content')
    <div class="mb-8 flex justify-between items-end">
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

    <!-- Buscador (por título o EAN) -->
    <div class="bg-slate-900 border border-slate-800 rounded-xl p-4 mb-6">
        <form action="{{ route('web.games.index') }}" method="GET" class="flex gap-3">
            <input type="text" name="q" value="{{ $query ?? '' }}" placeholder="Buscar por título o EAN..."
                class="w-full rounded-lg border border-slate-700 bg-slate-800 text-slate-100 placeholder:text-slate-500 px-4 py-2.5 focus:border-indigo-500 focus:ring-indigo-500 outline-none text-sm">
            <button type="submit" class="bg-slate-700 text-slate-100 px-6 py-2.5 rounded-lg text-sm font-medium hover:bg-slate-600 transition-colors whitespace-nowrap">
                Buscar
            </button>
            @if(!empty($query))
                <a href="{{ route('web.games.index') }}" class="flex items-center text-sm font-medium text-slate-400 hover:text-slate-100 whitespace-nowrap">
                    Limpiar
                </a>
            @endif
        </form>
    </div>

    <!-- Contenedor de la Tabla -->
    <div class="bg-slate-900 border border-slate-800 rounded-xl overflow-hidden">
        <table class="min-w-full divide-y divide-slate-800">
            <thead class="bg-slate-800/50">
                <tr>
                    <th scope="col" class="px-6 py-3.5 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Título</th>
                    <th scope="col" class="px-6 py-3.5 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Plataforma</th>
                    <th scope="col" class="px-6 py-3.5 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Estado</th>
                    <th scope="col" class="px-6 py-3.5 text-center text-xs font-semibold text-slate-400 uppercase tracking-wider">Valoración</th>
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

                        <!-- Valoración -->
                        <td class="px-6 py-4 whitespace-nowrap text-center">
                            @if($game->rating)
                                <div class="inline-flex items-center gap-1 font-semibold text-slate-300 text-sm">
                                    {{ $game->rating }}
                                    <x-gicon name="star" filled class="text-[16px] text-amber-400" />
                                </div>
                            @else
                                <span class="text-sm text-slate-500">-</span>
                            @endif
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
                        <td colspan="5" class="px-6 py-12 text-center text-slate-500 text-sm">
                            @if(!empty($query))
                                No hay coincidencias para "{{ $query }}".
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
