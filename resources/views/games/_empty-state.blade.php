<!-- Contenido del estado vacío del listado (games/_results.blade.php), en un
     parcial aparte porque se repite igual en las tres vistas del listado
     (tarjetas, tabla, estantería) — cada una solo envuelve esto con su
     propio contenedor (rounded-xl / <td colspan> / col-span-full). $hasActiveFilters
     ya está en el scope del padre (ver GameController::index()), Blade lo
     comparte con @@include sin pasarlo a mano. -->
<div class="flex flex-col items-center gap-3 text-center">
    <x-gicon name="{{ $hasActiveFilters ? 'search' : 'sports_esports' }}" class="text-[32px] text-slate-700" />

    <p class="text-slate-500 text-sm">
        @if($hasActiveFilters)
            No hay juegos que coincidan con la búsqueda o los filtros aplicados.
        @else
            No hay juegos registrados todavía.
        @endif
    </p>

    <div class="flex flex-wrap items-center justify-center gap-2">
        @if($hasActiveFilters)
            <a href="{{ route('web.games.index') }}"
                class="inline-flex items-center gap-1.5 rounded-lg px-4 py-2 bg-slate-700 text-slate-100 text-sm font-medium hover:bg-slate-600 transition-colors">
                <x-gicon name="close" class="text-[16px]" />
                Quitar filtros
            </a>
        @else
            <a href="{{ route('web.games.create') }}"
                class="inline-flex items-center gap-1.5 rounded-lg px-4 py-2 bg-(--color-navbar) text-white text-sm font-medium hover:bg-(--color-navbar-hover) transition-colors">
                <x-gicon name="add" class="text-[16px]" />
                Añadir tu primer juego
            </a>
            <a href="{{ route('web.games.import') }}"
                class="inline-flex items-center gap-1.5 rounded-lg px-4 py-2 bg-slate-700 text-slate-100 text-sm font-medium hover:bg-slate-600 transition-colors">
                <x-gicon name="upload_file" class="text-[16px]" />
                Importar colección
            </a>
        @endif
    </div>
</div>
