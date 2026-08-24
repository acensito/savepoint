@extends('layouts.app')

@section('content')
    <div class="mb-8 flex flex-col sm:flex-row sm:justify-between sm:items-end gap-4">
        <div>
            <h1 class="text-3xl font-bold text-slate-100 tracking-tight">Plataformas</h1>
            <p class="text-slate-400 mt-1">La etiqueta y los colores son los que se muestran en el chip de tu colección.</p>
        </div>

        <div class="flex gap-2">
            <a href="{{ route('web.manufacturers.index') }}" class="text-sm font-medium text-slate-400 hover:text-slate-100 self-center">
                Ver fabricantes →
            </a>
            <a href="{{ route('web.platforms.create') }}" class="bg-[var(--color-navbar)] text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-[var(--color-navbar-hover)] transition-colors">
                + Nueva Plataforma
            </a>
        </div>
    </div>

    <!-- Tarjetas: listado en pantallas estrechas, sin scroll horizontal -->
    <div class="md:hidden space-y-2.5">
        @forelse($platforms as $platform)
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4">
                <div class="flex items-start justify-between gap-3">
                    <x-platform-chip :platform="$platform" />

                    <div class="flex items-center gap-1 flex-shrink-0">
                        <a href="{{ route('web.platforms.edit', $platform->id) }}"
                            class="flex items-center justify-center w-8 h-8 rounded-full text-slate-400 hover:bg-slate-800 hover:text-indigo-400 active:bg-slate-800 transition-colors"
                            aria-label="Editar {{ $platform->name }}">
                            <x-gicon name="edit" class="text-[18px]" />
                        </a>

                        <form action="{{ route('web.platforms.destroy', $platform->id) }}" method="POST" class="js-confirm-delete"
                            data-confirm-title="Borrar plataforma"
                            data-confirm-message="«{{ $platform->name }}» se borrará. Los juegos asociados se quedarán sin plataforma.">
                            @csrf
                            @method('DELETE')
                            <button type="submit"
                                class="flex items-center justify-center w-8 h-8 rounded-full text-slate-400 hover:bg-slate-800 hover:text-red-400 active:bg-slate-800 transition-colors"
                                aria-label="Borrar {{ $platform->name }}">
                                <x-gicon name="delete" class="text-[18px]" />
                            </button>
                        </form>
                    </div>
                </div>

                <p class="text-sm text-slate-400 mt-2">{{ $platform->name }} · {{ $platform->manufacturer?->name ?? 'Sin fabricante' }}</p>
            </div>
        @empty
            <div class="bg-slate-900 border border-slate-800 rounded-xl px-6 py-12 text-center text-slate-500 text-sm">
                No hay plataformas registradas todavía.
            </div>
        @endforelse
    </div>

    <!-- Tabla: listado en pantallas medianas y grandes -->
    <div class="hidden md:block bg-slate-900 border border-slate-800 rounded-xl overflow-x-auto">
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
                                <form action="{{ route('web.platforms.destroy', $platform->id) }}" method="POST" class="inline js-confirm-delete"
                                    data-confirm-title="Borrar plataforma"
                                    data-confirm-message="«{{ $platform->name }}» se borrará. Los juegos asociados se quedarán sin plataforma.">
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
