@extends('layouts.app')

@section('content')
    <div class="mb-8 flex flex-col sm:flex-row sm:justify-between sm:items-end gap-4">
        <div>
            <h1 class="text-3xl font-bold text-slate-100 tracking-tight">Fabricantes</h1>
            <p class="text-slate-400 mt-1">Define aquí el color de marca que heredan sus plataformas.</p>
        </div>

        <div class="flex gap-2">
            <a href="{{ route('web.platforms.index') }}" class="text-sm font-medium text-slate-400 hover:text-slate-100 self-center">
                Ver plataformas →
            </a>
            <a href="{{ route('web.manufacturers.create') }}" class="bg-[var(--color-navbar)] text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-[var(--color-navbar-hover)] transition-colors">
                + Nuevo Fabricante
            </a>
        </div>
    </div>

    <!-- Tarjetas: listado en pantallas estrechas, sin scroll horizontal -->
    <div class="md:hidden space-y-2.5">
        @forelse($manufacturers as $manufacturer)
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4">
                <div class="flex items-start justify-between gap-3">
                    <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-semibold border"
                        style="background-color: {{ $manufacturer->bg_color }}; color: {{ $manufacturer->text_color }}; border-color: {{ $manufacturer->border_color }};">
                        {{ $manufacturer->name }}
                    </span>

                    <div class="flex items-center gap-1 flex-shrink-0">
                        <a href="{{ route('web.manufacturers.edit', $manufacturer->id) }}"
                            class="flex items-center justify-center w-8 h-8 rounded-full text-slate-400 hover:bg-slate-800 hover:text-indigo-400 active:bg-slate-800 transition-colors"
                            aria-label="Editar {{ $manufacturer->name }}">
                            <x-gicon name="edit" class="text-[18px]" />
                        </a>

                        <form action="{{ route('web.manufacturers.destroy', $manufacturer->id) }}" method="POST" class="js-confirm-delete"
                            data-confirm-title="Borrar fabricante"
                            data-confirm-message="«{{ $manufacturer->name }}» se borrará. Sus plataformas se quedarán sin fabricante asignado.">
                            @csrf
                            @method('DELETE')
                            <button type="submit"
                                class="flex items-center justify-center w-8 h-8 rounded-full text-slate-400 hover:bg-slate-800 hover:text-red-400 active:bg-slate-800 transition-colors"
                                aria-label="Borrar {{ $manufacturer->name }}">
                                <x-gicon name="delete" class="text-[18px]" />
                            </button>
                        </form>
                    </div>
                </div>

                <p class="text-sm text-slate-400 mt-2">
                    {{ $manufacturer->platforms_count }} {{ \Illuminate\Support\Str::plural('plataforma', $manufacturer->platforms_count) }}
                </p>
            </div>
        @empty
            <div class="bg-slate-900 border border-slate-800 rounded-xl px-6 py-12 text-center text-slate-500 text-sm">
                No hay fabricantes registrados todavía.
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
                    <th scope="col" class="px-6 py-3.5 text-center text-xs font-semibold text-slate-400 uppercase tracking-wider">Plataformas</th>
                    <th scope="col" class="px-6 py-3.5 text-right text-xs font-semibold text-slate-400 uppercase tracking-wider">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800">
                @forelse($manufacturers as $manufacturer)
                    <tr class="hover:bg-slate-800/40 transition-colors">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-semibold border"
                                style="background-color: {{ $manufacturer->bg_color }}; color: {{ $manufacturer->text_color }}; border-color: {{ $manufacturer->border_color }};">
                                {{ $manufacturer->name }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-slate-100">{{ $manufacturer->name }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-slate-400">{{ $manufacturer->platforms_count }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <div class="flex items-center justify-end gap-3">
                                <a href="{{ route('web.manufacturers.edit', $manufacturer->id) }}" class="text-indigo-400 hover:text-indigo-300 transition-colors">
                                    Editar
                                </a>
                                <form action="{{ route('web.manufacturers.destroy', $manufacturer->id) }}" method="POST" class="inline js-confirm-delete"
                                    data-confirm-title="Borrar fabricante"
                                    data-confirm-message="«{{ $manufacturer->name }}» se borrará. Sus plataformas se quedarán sin fabricante asignado.">
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
                            No hay fabricantes registrados todavía.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
