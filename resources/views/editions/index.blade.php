@extends('layouts.app')

@section('content')
    <div class="mb-8 flex flex-col sm:flex-row sm:justify-between sm:items-end gap-4">
        <div>
            <h1 class="text-3xl font-bold text-slate-100 tracking-tight">Ediciones</h1>
            <p class="text-slate-400 mt-1">Normal, especial, coleccionista, digital, CIB... y en qué plataformas existe cada una.</p>
        </div>

        <div class="flex gap-2">
            <a href="{{ route('web.editions.create') }}" class="bg-(--color-navbar) text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-(--color-navbar-hover) transition-colors">
                + Nueva Edición
            </a>
        </div>
    </div>

    <!-- Tarjetas: listado en pantallas estrechas, sin scroll horizontal -->
    <div class="md:hidden space-y-2.5">
        @forelse($editions as $edition)
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h3 class="text-[15px] font-bold text-slate-100 truncate flex items-center gap-1.5">
                            <x-gicon :name="$edition->formatIcon()" :title="$edition->formatLabel()" class="text-[16px] text-slate-400" />
                            {{ $edition->name }}
                        </h3>
                        <p class="text-xs text-slate-500 mt-0.5">
                            {{ $edition->formatLabel() }} · {{ $edition->games_count }} {{ Str::plural('juego', $edition->games_count) }}
                        </p>
                    </div>

                    <div class="flex items-center gap-1 shrink-0">
                        <a href="{{ route('web.editions.edit', $edition->id) }}"
                            class="flex items-center justify-center w-8 h-8 rounded-full text-slate-400 hover:bg-slate-800 hover:text-indigo-400 active:bg-slate-800 transition-colors"
                            aria-label="Editar {{ $edition->name }}">
                            <x-gicon name="edit" class="text-[18px]" />
                        </a>

                        <form action="{{ route('web.editions.destroy', $edition->id) }}" method="POST" class="js-confirm-delete"
                            data-confirm-title="Borrar edición"
                            data-confirm-message="«{{ $edition->name }}» se borrará. Los juegos asociados se quedarán sin edición.">
                            @csrf
                            @method('DELETE')
                            <button type="submit"
                                class="flex items-center justify-center w-8 h-8 rounded-full text-slate-400 hover:bg-slate-800 hover:text-red-400 active:bg-slate-800 transition-colors"
                                aria-label="Borrar {{ $edition->name }}">
                                <x-gicon name="delete" class="text-[18px]" />
                            </button>
                        </form>
                    </div>
                </div>

                <div class="mt-2">
                    @forelse($edition->platforms as $platform)
                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium bg-slate-800 text-slate-300 border border-slate-700 mr-1 mb-1">
                            {{ $platform->name }}
                        </span>
                    @empty
                        <span class="text-xs text-slate-500 italic">Cualquier plataforma</span>
                    @endforelse
                </div>
            </div>
        @empty
            <div class="bg-slate-900 border border-slate-800 rounded-xl px-6 py-12 text-center text-slate-500 text-sm">
                No hay ediciones registradas todavía.
            </div>
        @endforelse
    </div>

    <!-- Tabla: listado en pantallas medianas y grandes -->
    <div class="hidden md:block bg-slate-900 border border-slate-800 rounded-xl overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-800">
            <thead class="bg-slate-800/50">
                <tr>
                    <th scope="col" class="px-6 py-3.5 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Nombre</th>
                    <th scope="col" class="px-6 py-3.5 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Formato</th>
                    <th scope="col" class="px-6 py-3.5 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Plataformas</th>
                    <th scope="col" class="px-6 py-3.5 text-center text-xs font-semibold text-slate-400 uppercase tracking-wider">Juegos</th>
                    <th scope="col" class="px-6 py-3.5 text-right text-xs font-semibold text-slate-400 uppercase tracking-wider">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800">
                @forelse($editions as $edition)
                    <tr class="hover:bg-slate-800/40 transition-colors">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-slate-100">{{ $edition->name }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-300">
                            <span class="inline-flex items-center gap-1.5">
                                <x-gicon :name="$edition->formatIcon()" :title="$edition->formatLabel()" class="text-[16px] text-slate-400" />
                                {{ $edition->formatLabel() }}
                            </span>
                        </td>
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
                                <form action="{{ route('web.editions.destroy', $edition->id) }}" method="POST" class="inline js-confirm-delete"
                                    data-confirm-title="Borrar edición"
                                    data-confirm-message="«{{ $edition->name }}» se borrará. Los juegos asociados se quedarán sin edición.">
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
                            No hay ediciones registradas todavía.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
