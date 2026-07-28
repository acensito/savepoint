@extends('layouts.app')

@section('content')
    <div class="mb-8 flex justify-between items-end">
        <div>
            <h1 class="text-3xl font-bold text-slate-100 tracking-tight">Plataformas</h1>
            <p class="text-slate-400 mt-1">La etiqueta y los colores son los que se muestran en el chip de tu colección.</p>
        </div>

        <div class="flex gap-2">
            <a href="{{ route('web.manufacturers.index') }}" class="text-sm font-medium text-slate-400 hover:text-slate-100 self-center">
                Ver fabricantes →
            </a>
            <a href="{{ route('web.platforms.create') }}" class="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-indigo-500 transition-colors">
                + Nueva Plataforma
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
                    <th scope="col" class="px-6 py-3.5 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Chip</th>
                    <th scope="col" class="px-6 py-3.5 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Nombre</th>
                    <th scope="col" class="px-6 py-3.5 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Fabricante</th>
                    <th scope="col" class="px-6 py-3.5 text-right text-xs font-semibold text-slate-400 uppercase tracking-wider">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800">
                @forelse($platforms as $platform)
                    <tr class="hover:bg-slate-800/40 transition-colors">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <x-platform-chip :platform="$platform" />
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-slate-100">{{ $platform->name }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-400">{{ $platform->manufacturer?->name ?? '—' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <div class="flex items-center justify-end gap-3">
                                <a href="{{ route('web.platforms.edit', $platform->id) }}" class="text-indigo-400 hover:text-indigo-300 transition-colors">
                                    Editar
                                </a>
                                <form action="{{ route('web.platforms.destroy', $platform->id) }}" method="POST" class="inline" onsubmit="return confirm('¿Borrar esta plataforma? Los juegos asociados se quedarán sin plataforma.');">
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
                            No hay plataformas registradas todavía.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
