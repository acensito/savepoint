@extends('layouts.app')

@section('content')
    <div class="mb-5 sm:mb-8 flex items-center justify-between gap-3">
        <div class="min-w-0 flex items-baseline gap-2">
            <h1 class="text-xl sm:text-3xl font-bold text-slate-100 tracking-tight truncate min-w-0">Mi Colección</h1>
            <span class="js-games-total text-xs sm:text-base text-slate-400 whitespace-nowrap flex-shrink-0">
                {{ $games->total() }} {{ \Illuminate\Support\Str::plural('juego', $games->total()) }} {{ \Illuminate\Support\Str::plural('registrado', $games->total()) }}.
            </span>
        </div>

        <div class="flex-shrink-0 flex items-center gap-2">
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

    <!-- Buscador: protagonista de la página (grande, a todo el ancho), visible
         siempre igual en cualquier tamaño de pantalla en vez de vivir plegado tras
         un acordeón en móvil, ya que es lo primero que se usa en la colección. Los
         filtros "Avanzado" (plataforma/estado/orden/paginación) siguen plegados
         tras su icono, dentro del propio parcial. -->
    <div class="bg-slate-900 border border-slate-800 rounded-xl p-4 mb-3">
        @include('games._filters')
    </div>

    <!-- Iconos de vista y de modo selección: secundarios frente al buscador, así
         que van más pequeños y agrupados en su propia línea, pegados a la esquina
         superior derecha justo encima de la tabla/tarjetas. Cuál de las vistas se
         ve lo decide app.css según la clase 'games-grid-view' en <html>, que estos
         botones alternan y persisten en localStorage. -->
    <div class="flex items-center justify-end gap-2 mb-3">
        <div class="inline-flex items-stretch h-8 bg-slate-900 border border-slate-800 rounded-lg overflow-hidden">
            <!-- Lista y compacta son variantes de la TABLA de escritorio: en móvil,
                 donde siempre se ve la tarjeta (no la tabla), no tienen ningún efecto,
                 así que se ocultan y solo queda el botón de estantería (que sí cambia
                 algo visible en cualquier ancho). -->
            <button type="button" id="games-view-list-btn" aria-label="Ver como lista" aria-pressed="true"
                class="hidden md:flex items-center justify-center w-8 text-indigo-400 hover:bg-slate-800 transition-colors">
                <x-gicon name="density_small" class="text-[15px]" />
            </button>
            <button type="button" id="games-view-compact-btn" aria-label="Ver como tabla compacta" aria-pressed="false"
                class="hidden md:flex items-center justify-center w-8 text-slate-500 hover:bg-slate-800 md:border-l md:border-slate-800 transition-colors">
                <x-gicon name="view_list" class="text-[15px]" />
            </button>
            <button type="button" id="games-view-grid-btn" aria-label="Ver como estantería" aria-pressed="false"
                class="flex items-center justify-center w-8 text-slate-500 hover:bg-slate-800 md:border-l md:border-slate-800 transition-colors">
                <x-gicon name="grid_view" class="text-[15px]" />
            </button>
        </div>

        <button type="button" id="selection-mode-toggle" aria-pressed="false" aria-label="Seleccionar varios juegos"
            class="flex items-center justify-center w-8 h-8 rounded-lg border border-slate-700 text-slate-400 hover:bg-slate-800 hover:text-slate-100 transition-colors">
            <x-gicon name="checklist" class="text-[16px]" />
        </button>
    </div>

    <!-- Contenedor permanente: nunca se destruye, solo se le reemplaza el
         innerHTML (ver refreshGamesResults en app.js) cuando el buscador
         filtra en vivo, para que los listeners delegados sobre él (acciones
         en bloque, edición rápida) no necesiten volver a engancharse. -->
    <div id="games-results">
        @include('games._results')
    </div>

    <!-- Espaciador para que el final del listado/paginación no quede tapado por la
         barra de estado, que al ser fixed ya no ocupa espacio en el flujo normal. -->
    <div class="h-10"></div>

    <!-- Barra de estado discreta: totales de TODA la colección (no del resultado
         filtrado de arriba), siempre fija al fondo de la pantalla (no solo al llegar
         al final del scroll). Su posición (left, para no tapar el sidebar; bottom)
         se define en app.css junto a las reglas del propio sidebar, ya que depende
         de si está colapsado o en modo drawer móvil igual que él. -->
    <div id="games-status-bar" class="bg-slate-950/95 backdrop-blur-sm border-t border-slate-800 text-xs text-slate-500 flex items-center justify-center gap-3 px-4 md:px-8 py-2">
        <span>{{ number_format($collectionTotals['count']) }} {{ \Illuminate\Support\Str::plural('juego', $collectionTotals['count']) }} en tu colección</span>
        @if($collectionTotals['spent'] > 0)
            <span class="text-slate-700">·</span>
            <span>{{ number_format($collectionTotals['spent'], 2, ',', '.') }} € invertidos</span>
        @endif
    </div>
@endsection
