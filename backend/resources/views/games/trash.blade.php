@extends('layouts.app')

@section('content')
    <div class="mb-8 flex flex-col sm:flex-row sm:justify-between sm:items-end gap-4">
        <div>
            <h1 class="text-3xl font-bold text-slate-100 tracking-tight">Papelera</h1>
            <p class="text-slate-400 mt-1">Juegos borrados que todavía se pueden restaurar.</p>
        </div>

        <a href="{{ route('web.games.index') }}" class="text-sm font-medium text-slate-400 hover:text-slate-100">
            ← Volver a mi colección
        </a>
    </div>

    <div class="bg-slate-900 border border-slate-800 rounded-xl overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-800">
            <thead class="bg-slate-800/50">
                <tr>
                    <th scope="col" class="px-6 py-3.5 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Título</th>
                    <th scope="col" class="px-6 py-3.5 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Plataforma</th>
                    <th scope="col" class="px-6 py-3.5 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Borrado</th>
                    <th scope="col" class="px-6 py-3.5 text-right text-xs font-semibold text-slate-400 uppercase tracking-wider">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800">
                @forelse($games as $game)
                    <tr class="hover:bg-slate-800/40 transition-colors">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-start gap-3">
                                <x-game-cover :game="$game" size="sm" />
                                <div class="text-sm font-bold text-slate-100 pt-1.5">{{ $game->title }}</div>
                            </div>
                        </td>

                        <td class="px-6 py-4 whitespace-nowrap">
                            <x-platform-chip :platform="$game->platform" />
                        </td>

                        <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-300">
                            {{ $game->deleted_at->format('d/m/Y H:i') }}
                        </td>

                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <div class="flex items-center justify-end gap-3">
                                <form action="{{ route('web.games.restore', $game->id) }}" method="POST" class="inline">
                                    @csrf
                                    <button type="submit" class="text-emerald-400 hover:text-emerald-300 transition-colors">
                                        Restaurar
                                    </button>
                                </form>

                                <form action="{{ route('web.games.force-delete', $game->id) }}" method="POST" class="inline js-confirm-delete"
                                    data-confirm-title="Eliminar definitivamente"
                                    data-confirm-message="«{{ $game->title }}» se eliminará para siempre, incluida su carátula. Esta acción no se puede deshacer."
                                    data-confirm-accept="Eliminar definitivamente">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-400 hover:text-red-300 transition-colors">
                                        Eliminar definitivamente
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-6 py-12 text-center text-slate-500 text-sm">
                            La papelera está vacía.
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
