@extends('layouts.app')

@php
    $activeFilters = array_filter([
        !empty($query),
        $platformId !== '',
        $playStatus !== '',
        $status !== '',
    ]);
    $hasActiveFilters = count($activeFilters) > 0;
    $activeFilterCount = count($activeFilters);
@endphp

@section('content')
    <div class="mb-5 sm:mb-8 flex items-center justify-between gap-3">
        <div class="min-w-0">
            <h1 class="text-xl sm:text-3xl font-bold text-slate-100 tracking-tight truncate">Mi Colección</h1>
            <p class="text-xs sm:text-base text-slate-400 mt-0.5 sm:mt-1">
                {{ $games->total() }} {{ \Illuminate\Support\Str::plural('juego', $games->total()) }} {{ \Illuminate\Support\Str::plural('registrado', $games->total()) }}.
            </p>
        </div>

        <div class="flex-shrink-0 flex items-center gap-2">
            <a href="{{ route('web.games.import') }}" aria-label="Importar colección"
                class="flex items-center justify-center w-10 h-10 rounded-full border border-slate-700 text-slate-400 hover:bg-slate-800 hover:text-slate-100 transition-colors">
                <x-gicon name="upload_file" class="text-[20px]" />
            </a>

            <a href="{{ route('web.games.trash') }}" aria-label="Papelera de reciclaje"
                class="flex items-center justify-center w-10 h-10 rounded-full border border-slate-700 text-slate-400 hover:bg-slate-800 hover:text-slate-100 transition-colors">
                <x-gicon name="delete" class="text-[20px]" />
            </a>

            <a href="{{ route('web.games.create') }}" aria-label="Añadir juego"
                class="hidden sm:flex items-center justify-center gap-1.5 rounded-lg px-4 py-2 bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-500 transition-colors">
                <x-gicon name="add" class="text-[18px]" />
                Añadir Juego
            </a>
        </div>
    </div>

    <!-- Botón flotante de "Añadir juego" en móvil: fijo en pantalla para que
         siga a mano al hacer scroll por una colección larga, en vez de tener
         que volver arriba a por el icono del header. En sm: y superior ya
         hay un botón normal en el header, así que este se oculta. -->
    <a href="{{ route('web.games.create') }}" aria-label="Añadir juego"
        class="sm:hidden fixed bottom-5 right-5 z-30 flex items-center justify-center w-14 h-14 rounded-full bg-indigo-600 text-white shadow-lg shadow-black/30 hover:bg-indigo-500 transition-colors">
        <x-gicon name="add" class="text-[26px]" />
    </a>

    <!-- Formulario "fantasma" de las acciones en bloque: no envuelve el listado (ya
         hay un <form> individual de borrar por fila/tarjeta, y anidar formularios
         no es válido en HTML). Las casillas y los controles de la barra se asocian
         a él desde cualquier punto del documento con el atributo form="bulk-form". -->
    <form id="bulk-form" method="POST">
        @csrf
    </form>

    <div id="bulk-bar" class="hidden flex items-center gap-3 flex-wrap bg-indigo-500/10 border border-indigo-500/30 rounded-xl px-4 py-3 mb-4">
        <span id="bulk-count" class="text-sm font-medium text-indigo-200 flex-1 min-w-[140px]">0 juegos seleccionados</span>

        <select name="play_status" form="bulk-form" class="rounded-lg border border-slate-700 bg-slate-800 text-slate-100 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500 outline-none">
            <option value="pending">Marcar como pendiente</option>
            <option value="playing">Marcar como jugando</option>
            <option value="finished">Marcar como terminado</option>
        </select>

        <button type="submit" form="bulk-form" formaction="{{ route('web.games.bulk-play-status') }}"
            class="bg-slate-700 text-slate-100 px-4 py-2 rounded-lg text-sm font-medium hover:bg-slate-600 transition-colors whitespace-nowrap">
            Aplicar
        </button>

        <button type="submit" form="bulk-form" formaction="{{ route('web.games.bulk-delete') }}"
            class="js-confirm-delete bg-red-600/10 border border-red-500/30 text-red-400 px-4 py-2 rounded-lg text-sm font-medium hover:bg-red-600/20 transition-colors whitespace-nowrap"
            data-confirm-title="Enviar a la papelera"
            data-confirm-message="Los juegos seleccionados se moverán a la papelera. Podrás restaurarlos más tarde.">
            Enviar a la papelera
        </button>
    </div>

    <!-- Buscador y filtros: colapsados tras un botón en móvil para no comerse la pantalla -->
    <details class="group md:hidden bg-slate-900 border border-slate-800 rounded-xl mb-6" {{ $hasActiveFilters ? 'open' : '' }}>
        <summary class="flex items-center justify-between gap-2 px-4 py-3 cursor-pointer select-none">
            <span class="flex items-center gap-2 text-sm font-medium text-slate-200">
                <x-gicon name="filter_list" class="text-[18px] text-slate-400" />
                Buscar y filtrar
                @if($hasActiveFilters)
                    <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-indigo-500 text-white text-[11px] font-bold">{{ $activeFilterCount }}</span>
                @endif
            </span>
            <x-gicon name="expand_more" class="text-[20px] text-slate-500 transition-transform duration-200 group-open:rotate-180" />
        </summary>

        <div class="px-4 pb-4">
            @include('games._filters')
        </div>
    </details>

    <div class="hidden md:block bg-slate-900 border border-slate-800 rounded-xl p-4 mb-6">
        @include('games._filters')
    </div>

    <!-- Vista habitual (tarjetas/tabla) vs. estantería (grid de carátulas grandes):
         cuál de las dos se ve lo decide app.css según la clase 'games-grid-view' en
         <html>, que este botón alterna y persiste en localStorage. -->
    <div class="flex justify-end mb-4">
        <div class="inline-flex items-center gap-1 bg-slate-900 border border-slate-800 rounded-lg p-1">
            <button type="button" id="games-view-list-btn" aria-label="Ver como lista" aria-pressed="true"
                class="flex items-center justify-center w-8 h-8 rounded-md text-indigo-400 hover:bg-slate-800 transition-colors">
                <x-gicon name="view_list" class="text-[18px]" />
            </button>
            <button type="button" id="games-view-grid-btn" aria-label="Ver como estantería" aria-pressed="false"
                class="flex items-center justify-center w-8 h-8 rounded-md text-slate-500 hover:bg-slate-800 transition-colors">
                <x-gicon name="grid_view" class="text-[18px]" />
            </button>
        </div>
    </div>

    <div id="view-list">
    <!-- Tarjetas: listado en pantallas estrechas, sin scroll horizontal -->
    <div class="md:hidden space-y-2.5">
        @forelse($games as $game)
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-3 flex items-start gap-3">
                <input type="checkbox" form="bulk-form" name="game_ids[]" value="{{ $game->id }}"
                    class="js-bulk-checkbox mt-1 w-4 h-4 flex-shrink-0 rounded border-slate-600 bg-slate-800 text-indigo-600 focus:ring-indigo-500"
                    aria-label="Seleccionar {{ $game->title }}">

                <x-game-cover :game="$game" size="lg" class="!w-14 !rounded-xl !text-lg" />

                <div class="flex-1 min-w-0">
                    <h3 class="text-[15px] font-bold text-slate-100 truncate leading-snug">{{ $game->title }}</h3>

                    <div class="mt-1 flex items-center gap-1.5 flex-wrap">
                        <x-platform-chip :platform="$game->platform" class="!px-2 !py-0.5 !text-[10px]" />

                        <span class="inline-flex items-center gap-1 text-[11px] font-medium {{ $game->play_status === 'finished' ? 'text-emerald-400' : 'text-slate-500' }}">
                            @if($game->play_status === 'finished')
                                <x-gicon name="check_circle" class="text-[13px]" />
                            @else
                                <x-gicon name="schedule" class="text-[13px]" />
                            @endif
                            {{ $game->play_status ? ucfirst($game->play_status) : 'Pendiente' }}
                        </span>
                    </div>

                    <div class="mt-1.5 flex items-center gap-2">
                        <x-star-rating :rating="$game->rating" size="text-[11px]" />
                        @if($game->price_paid !== null)
                            <span class="text-[11px] font-semibold text-slate-400 tabular-nums">{{ number_format($game->price_paid, 2, ',', '.') }} €</span>
                        @endif
                    </div>
                </div>

                <div class="flex flex-col items-center gap-1 flex-shrink-0">
                    <a href="{{ route('web.games.edit', $game->id) }}"
                        class="flex items-center justify-center w-8 h-8 rounded-full text-slate-400 hover:bg-slate-800 hover:text-indigo-400 active:bg-slate-800 transition-colors"
                        aria-label="Editar {{ $game->title }}">
                        <x-gicon name="edit" class="text-[18px]" />
                    </a>

                    <form action="{{ route('web.games.destroy', $game->id) }}" method="POST" class="js-confirm-delete"
                        data-confirm-title="Enviar a la papelera"
                        data-confirm-message="«{{ $game->title }}» se moverá a la papelera. Podrás restaurarlo más tarde.">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                            class="flex items-center justify-center w-8 h-8 rounded-full text-slate-400 hover:bg-slate-800 hover:text-red-400 active:bg-slate-800 transition-colors"
                            aria-label="Borrar {{ $game->title }}">
                            <x-gicon name="delete" class="text-[18px]" />
                        </button>
                    </form>
                </div>
            </div>
        @empty
            <div class="bg-slate-900 border border-slate-800 rounded-xl px-6 py-12 text-center text-slate-500 text-sm">
                @if($hasActiveFilters)
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
                    <th scope="col" class="pl-6 pr-2 py-3.5 w-4">
                        <input type="checkbox" id="bulk-select-all"
                            class="w-4 h-4 rounded border-slate-600 bg-slate-800 text-indigo-600 focus:ring-indigo-500"
                            aria-label="Seleccionar todos los juegos de esta página">
                    </th>
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
                        <!-- Selección para acciones en bloque -->
                        <td class="pl-6 pr-2 py-4">
                            <input type="checkbox" form="bulk-form" name="game_ids[]" value="{{ $game->id }}"
                                class="js-bulk-checkbox w-4 h-4 rounded border-slate-600 bg-slate-800 text-indigo-600 focus:ring-indigo-500"
                                aria-label="Seleccionar {{ $game->title }}">
                        </td>

                        <!-- Título -->
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-start gap-3">
                                <x-game-cover :game="$game" size="sm" />
                                <div class="text-sm font-bold text-slate-100 pt-1.5">{{ $game->title }}</div>
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
                                <x-gicon name="check_circle" class="text-[18px] text-emerald-400" title="Con Manual" />
                            @elseif($game->manual_status === 'booklet')
                                <x-gicon name="description" class="text-[18px] text-emerald-400" title="Folleto" />
                            @elseif($game->manual_status === 'missing')
                                <x-gicon name="warning" class="text-[18px] text-amber-400" title="Sin Manual" />
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

                                <form action="{{ route('web.games.destroy', $game->id) }}" method="POST" class="inline js-confirm-delete"
                                    data-confirm-title="Enviar a la papelera"
                                    data-confirm-message="«{{ $game->title }}» se moverá a la papelera. Podrás restaurarlo más tarde.">
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
                        <td colspan="11" class="px-6 py-12 text-center text-slate-500 text-sm">
                            @if($hasActiveFilters)
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
    </div>

    <!-- Estantería: grid de carátulas grandes, alternativa visual a la tabla/tarjetas
         de arriba (oculta por defecto, ver #view-grid en app.css). -->
    <div id="view-grid" class="grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
        @forelse($games as $game)
            <div class="group relative">
                <input type="checkbox" form="bulk-form" name="game_ids[]" value="{{ $game->id }}"
                    class="js-bulk-checkbox absolute top-2 left-2 z-10 w-4 h-4 rounded border-slate-500 bg-slate-900/80 text-indigo-600 focus:ring-indigo-500"
                    aria-label="Seleccionar {{ $game->title }}">

                <a href="{{ route('web.games.edit', $game->id) }}" class="block">
                    <x-game-cover :game="$game" size="lg" class="!w-full !aspect-[3/4] !h-auto !rounded-xl !text-3xl group-hover:opacity-80 transition-opacity" />
                </a>

                <div class="mt-2">
                    <a href="{{ route('web.games.edit', $game->id) }}" class="block text-sm font-semibold text-slate-100 truncate hover:text-indigo-300 transition-colors">
                        {{ $game->title }}
                    </a>
                    <div class="mt-1 flex items-center justify-between gap-2">
                        <x-platform-chip :platform="$game->platform" class="!px-2 !py-0.5 !text-[10px]" />
                        <x-star-rating :rating="$game->rating" size="text-[11px]" />
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-full bg-slate-900 border border-slate-800 rounded-xl px-6 py-12 text-center text-slate-500 text-sm">
                @if($hasActiveFilters)
                    No hay juegos que coincidan con la búsqueda o los filtros aplicados.
                @else
                    No hay juegos registrados todavía.
                @endif
            </div>
        @endforelse
    </div>

    @if($games->hasPages())
        <div class="mt-6">
            {{ $games->links() }}
        </div>
    @endif
@endsection
