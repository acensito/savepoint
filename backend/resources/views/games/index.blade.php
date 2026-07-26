@extends('layouts.app')

@section('content')
    <div class="mb-8 flex justify-between items-end">
        <div>
            <h1 class="text-3xl font-bold text-slate-900 tracking-tight">Mi Colección</h1>
            <p class="text-slate-500 mt-1">Tienes {{ $games->count() }} juegos registrados.</p>
        </div>
        
        <div class="flex gap-2">
            <a href="{{ route('web.games.search') }}" class="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-indigo-700 transition-colors">
                + Añadir Juego
            </a>
        </div>
    </div>

    <!-- Contenedor de la Tabla -->
    <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
                <tr>
                    <th scope="col" class="px-6 py-3.5 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Título</th>
                    <th scope="col" class="px-6 py-3.5 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Plataforma</th>
                    <th scope="col" class="px-6 py-3.5 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Estado</th>
                    <th scope="col" class="px-6 py-3.5 text-center text-xs font-semibold text-slate-600 uppercase tracking-wider">Valoración</th>
                    <th scope="col" class="px-6 py-3.5 text-right text-xs font-semibold text-slate-600 uppercase tracking-wider">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 bg-white">
                @forelse($games as $game)
                    <tr class="hover:bg-slate-50/50 transition-colors">
                        <!-- Título -->
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-bold text-slate-900">{{ $game->title }}</div>
                        </td>

                        <!-- Plataforma -->
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-semibold bg-indigo-50 text-indigo-700">
                                <x-heroicon-o-cpu-chip class="w-4 h-4" />
                                {{ $game->platform->name ?? 'Sin plataforma' }}
                            </span>
                        </td>

                        <!-- Estado -->
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center gap-1.5 {{ $game->play_status === 'finished' ? 'text-emerald-600' : 'text-slate-500' }}">
                                @if($game->play_status === 'finished')
                                    <x-heroicon-o-check-circle class="w-5 h-5" />
                                @else
                                    <x-heroicon-o-clock class="w-5 h-5" />
                                @endif
                                <span class="text-sm font-medium capitalize">
                                    {{ $game->play_status ?? 'Pendiente' }}
                                </span>
                            </div>
                        </td>

                        <!-- Valoración -->
                        <td class="px-6 py-4 whitespace-nowrap text-center">
                            @if($game->rating)
                                <div class="inline-flex items-center gap-1 font-semibold text-slate-700 text-sm">
                                    {{ $game->rating }}
                                    <x-heroicon-s-star class="w-4 h-4 text-amber-400" />
                                </div>
                            @else
                                <span class="text-sm text-slate-400">-</span>
                            @endif
                        </td>

                        <!-- Acciones (Editar y Borrar) -->
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <div class="flex items-center justify-end gap-3">
                                <a href="{{ route('web.games.edit', $game->id) }}" class="text-indigo-600 hover:text-indigo-900 transition-colors">
                                    Editar
                                </a>

                                <form action="{{ route('web.games.destroy', $game->id) }}" method="POST" class="inline" onsubmit="return confirm('¿Seguro que quieres enviar este juego a la papelera?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:text-red-900 transition-colors">
                                        Borrar
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-12 text-center text-slate-500 text-sm">
                            No hay juegos registrados todavía.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection