@extends('layouts.app')

@section('content')
    <div class="mb-8 flex justify-between items-end">
        <div>
            <h1 class="text-3xl font-bold text-slate-100 tracking-tight">Ediciones</h1>
            <p class="text-slate-400 mt-1">Normal, especial, coleccionista, digital, CIB... y en qué plataformas existe cada una.</p>
        </div>

        <div class="flex gap-2">
            <a href="{{ route('web.editions.create') }}" class="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-indigo-500 transition-colors">
                + Nueva Edición
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-6 text-sm text-emerald-400 bg-emerald-500/10 border border-emerald-500/20 rounded-lg px-4 py-2.5">
            {{ session('success') }}
        </div>
    @endif

    <div class="bg-slate-900 border border-slate-800 rounded-xl overflow-hidden">
        <table class="min-w-full divide-y divide-slate-800">
            <thead class="bg-slate-800/50">
                <tr>
                    <th scope="col" class="px-6 py-3.5 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Nombre</th>
                    <th scope="col" class="px-6 py-3.5 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Plataformas</th>
                    <th scope="col" class="px-6 py-3.5 text-center text-xs font-semibold text-slate-400 uppercase tracking-wider">Juegos</th>
                    <th scope="col" class="px-6 py-3.5 text-right text-xs font-semibold text-slate-400 uppercase tracking-wider">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800">
                @forelse($editions as $edition)
                    <tr class="hover:bg-slate-800/40 transition-colors">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-slate-100">{{ $edition->name }}</td>
                        <td class="px-6 py-4">
                            @forelse($edition->platforms as $platform)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium bg-slate-800 text-slate-300 border border-slate-700 mr-1 mb-1">
                                    {{ $platform->name }}
                                </span>
                            @empty
                                <span class="text-sm text-slate-500 italic">Cualquier plataforma</span>
                            @endforelse
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-slate-400">{{ $edition->games_count }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <div class="flex items-center justify-end gap-3">
                                <a href="{{ route('web.editions.edit', $edition->id) }}" class="text-indigo-400 hover:text-indigo-300 transition-colors">
                                    Editar
                                </a>
                                <form action="{{ route('web.editions.destroy', $edition->id) }}" method="POST" class="inline" onsubmit="return confirm('¿Borrar esta edición? Los juegos asociados se quedarán sin edición.');">
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
                        <td colspan="4" class="px-6 py-12 text-center text-slate-500 text-sm">
                            No hay ediciones registradas todavía.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
