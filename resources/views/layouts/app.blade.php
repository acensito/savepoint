<!DOCTYPE html>
@php
    // Tema y vista de la colección son ajustes de cuenta (ver Ajustes /
    // PanelController), no de localStorage: se pintan aquí directamente para
    // que lleguen correctos en el primer HTML, sin depender de un script
    // bloqueante ni arriesgar un parpadeo al cargar.
    $htmlClasses = collect([
        auth()->user()->theme === 'light' ? 'light' : null,
        match (auth()->user()->games_view) {
            'grid' => 'games-grid-view',
            'compact' => 'games-compact-view',
            default => null,
        },
    ])->filter()->implode(' ');
@endphp
<html lang="es" class="{{ $htmlClasses }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>SavePoint - Mi Colección</title>
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#4f46e5">
    <link rel="icon" type="image/png" sizes="192x192" href="/icons/icon-192.png">
    <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="SavePoint">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Instrument+Sans:wght@400;500;600&family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=swap" rel="stylesheet">
    <script nonce="{{ $cspNonce }}">
        // Bloqueante a propósito: aplica el estado guardado del sidebar antes del
        // primer pintado para que no haya parpadeo (expandido/plegado) al cargar o
        // al navegar entre páginas. Tema y vista de la colección ya no viven aquí:
        // se pintan server-side arriba, en la clase de <html> (ver Ajustes).
        (function () {
            try {
                if (localStorage.getItem('sp:sidebarCollapsed') === '1') {
                    document.documentElement.classList.add('sidebar-collapsed');
                }
            } catch (e) {}
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-indigo-600 text-slate-300 antialiased">

    <div class="h-screen flex flex-col overflow-hidden">

        <!-- Navbar: ancho completo, fina -->
        <header class="h-12 flex-shrink-0 flex items-center justify-between gap-3 px-5">
            <div class="flex items-center gap-2 min-w-0">
                <button type="button" id="sidebar-mobile-toggle"
                    class="md:hidden flex items-center justify-center w-8 h-8 -ml-1.5 rounded-lg text-indigo-100 hover:bg-white/10 transition-colors flex-shrink-0"
                    aria-expanded="false" aria-controls="sidebar" aria-label="Abrir menú">
                    <x-gicon name="menu" class="text-[22px]" />
                </button>

                <a href="{{ route('web.games.index') }}" class="flex items-center gap-2 text-white min-w-0">
                    <x-gicon name="joystick" class="text-[22px] flex-shrink-0" />
                    <span class="text-base font-bold tracking-tight truncate">SavePoint</span>
                </a>
            </div>

            <div class="flex items-center gap-4 flex-shrink-0">
                <button type="button" id="quick-search-trigger"
                    class="js-quick-search-trigger flex items-center justify-center w-8 h-8 rounded-lg text-indigo-100 hover:bg-white/10 hover:text-white transition-colors"
                    aria-label="Buscar (Ctrl+K)" title="Buscar (Ctrl+K)">
                    <x-gicon name="search" class="text-[20px]" />
                </button>

                <button type="button" class="js-theme-toggle flex items-center justify-center w-8 h-8 rounded-lg text-indigo-100 hover:bg-white/10 hover:text-white transition-colors"
                    aria-label="Cambiar tema">
                    <x-gicon name="light_mode" class="text-[20px]" />
                </button>

                <form action="{{ route('web.logout') }}" method="POST" class="flex items-center gap-4">
                    <a href="{{ route('web.profile.edit') }}" class="flex items-center gap-1.5 text-sm text-indigo-100 hover:text-white transition-colors {{ request()->routeIs('web.profile.*') ? 'text-white font-medium' : '' }}">
                        @if(auth()->user()->avatarUrl())
                            <img src="{{ auth()->user()->avatarUrl() }}"
                                 alt="Avatar"
                                 class="w-6 h-6 rounded-full object-cover flex-shrink-0">
                        @else
                            <div class="w-6 h-6 rounded-full bg-white/20 flex items-center justify-center flex-shrink-0 overflow-hidden">
                                <x-gicon name="person" :filled="true" class="text-[16px] text-white" />
                            </div>
                        @endif
                        <span class="hidden sm:inline">{{ auth()->user()->email }}</span>
                    </a>
                    @csrf
                    <button type="submit" class="flex items-center gap-1.5 text-sm font-medium text-indigo-100 hover:text-white transition-colors">
                        <x-gicon name="logout" class="text-[18px]" />
                        <span class="hidden sm:inline">Salir</span>
                    </button>
                </form>
            </div>
        </header>

        <!-- Cuerpo: sidebar + contenido, esquinas superiores redondeadas -->
        <div class="flex-1 flex overflow-hidden rounded-t-[8px]">

            <!-- Fondo oscuro tras el sidebar cuando el drawer móvil está abierto; tocarlo lo cierra -->
            <div id="sidebar-backdrop"></div>

            <aside id="sidebar" class="sidebar flex-shrink-0 bg-slate-900 border-r border-slate-800 flex flex-col overflow-y-auto transition-[width] duration-200 ease-in-out">
                <div class="flex items-center justify-between px-3 py-2">
                    <span class="md:hidden pl-1 text-sm font-semibold text-slate-200">Menú</span>

                    <button type="button" id="sidebar-toggle"
                        class="hidden md:flex items-center justify-center w-8 h-8 rounded-lg text-slate-400 hover:bg-slate-800 hover:text-slate-100 transition-colors"
                        aria-expanded="true" aria-controls="sidebar" aria-label="Contraer menú">
                        <x-gicon id="sidebar-toggle-icon" name="chevron_left" class="text-[20px] transition-transform duration-200" />
                    </button>

                    <button type="button" id="sidebar-mobile-close"
                        class="md:hidden flex items-center justify-center w-8 h-8 rounded-lg text-slate-400 hover:bg-slate-800 hover:text-slate-100 transition-colors"
                        aria-label="Cerrar menú">
                        <x-gicon name="close" class="text-[20px]" />
                    </button>
                </div>

                <nav class="flex-1 px-3 py-1 space-y-1">
                    <a href="{{ route('web.games.index') }}" title="Colección"
                        class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors {{ request()->routeIs('web.games.*') ? 'bg-indigo-500/10 text-indigo-300' : 'text-slate-400 hover:bg-slate-800 hover:text-slate-100' }}">
                        <x-gicon name="sports_esports" class="text-[20px]" />
                        <span class="sidebar-label">Colección</span>
                    </a>

                    <!-- Separadores con inset propio (nunca a ras del borde del sidebar,
                         ni en móvil ni en escritorio, ni compacto ni normal): heredan el
                         px-3 de <nav> y no llevan -mx que lo cancele. -->
                    <div class="my-2 border-t border-slate-800"></div>

                    <a href="{{ route('web.wishlist.index') }}" title="Lista de deseos"
                        class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors {{ request()->routeIs('web.wishlist.*') ? 'bg-indigo-500/10 text-indigo-300' : 'text-slate-400 hover:bg-slate-800 hover:text-slate-100' }}">
                        <x-gicon name="favorite" class="text-[20px]" />
                        <span class="sidebar-label">Lista de deseos</span>
                    </a>

                    <div class="my-2 border-t border-slate-800"></div>

                    <a href="{{ route('web.commissions.index') }}" title="Encargos"
                        class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors {{ request()->routeIs('web.commissions.*') ? 'bg-indigo-500/10 text-indigo-300' : 'text-slate-400 hover:bg-slate-800 hover:text-slate-100' }}">
                        <x-gicon name="local_shipping" class="text-[20px]" />
                        <span class="sidebar-label">Encargos</span>
                    </a>

                    <div class="my-2 border-t border-slate-800"></div>

                    <a href="{{ route('web.for-sale.index') }}" title="En venta"
                        class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors {{ request()->routeIs('web.for-sale.*') ? 'bg-indigo-500/10 text-indigo-300' : 'text-slate-400 hover:bg-slate-800 hover:text-slate-100' }}">
                        <x-gicon name="sell" class="text-[20px]" />
                        <span class="sidebar-label">En venta</span>
                    </a>

                    <a href="{{ route('web.sales.index') }}" title="Ventas"
                        class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors {{ request()->routeIs('web.sales.*') ? 'bg-indigo-500/10 text-indigo-300' : 'text-slate-400 hover:bg-slate-800 hover:text-slate-100' }}">
                        <x-gicon name="paid" class="text-[20px]" />
                        <span class="sidebar-label">Ventas</span>
                    </a>

                    <div class="my-2 border-t border-slate-800"></div>

                    <a href="{{ route('web.platforms.index') }}" title="Plataformas"
                        class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors {{ request()->routeIs('web.platforms.*') ? 'bg-indigo-500/10 text-indigo-300' : 'text-slate-400 hover:bg-slate-800 hover:text-slate-100' }}">
                        <x-gicon name="memory" class="text-[20px]" />
                        <span class="sidebar-label">Plataformas</span>
                    </a>

                    <a href="{{ route('web.manufacturers.index') }}" title="Fabricantes"
                        class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors {{ request()->routeIs('web.manufacturers.*') ? 'bg-indigo-500/10 text-indigo-300' : 'text-slate-400 hover:bg-slate-800 hover:text-slate-100' }}">
                        <x-gicon name="factory" class="text-[20px]" />
                        <span class="sidebar-label">Fabricantes</span>
                    </a>

                    <a href="{{ route('web.editions.index') }}" title="Ediciones"
                        class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors {{ request()->routeIs('web.editions.*') ? 'bg-indigo-500/10 text-indigo-300' : 'text-slate-400 hover:bg-slate-800 hover:text-slate-100' }}">
                        <x-gicon name="interests" class="text-[20px]" />
                        <span class="sidebar-label">Ediciones</span>
                    </a>

                    <div class="my-2 border-t border-slate-800"></div>

                    <a href="{{ route('web.stats.index') }}" title="Estadísticas"
                        class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors {{ request()->routeIs('web.stats.*') ? 'bg-indigo-500/10 text-indigo-300' : 'text-slate-400 hover:bg-slate-800 hover:text-slate-100' }}">
                        <x-gicon name="bar_chart" class="text-[20px]" />
                        <span class="sidebar-label">Estadísticas</span>
                    </a>
                </nav>

                <div class="px-3 py-4 border-t border-slate-800 space-y-1">
                    <a href="{{ route('web.panel.index') }}" title="Panel de control"
                        class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors {{ request()->routeIs('web.panel.*', 'web.games.import*', 'web.games.trash') ? 'bg-indigo-500/10 text-indigo-300' : 'text-slate-400 hover:bg-slate-800 hover:text-slate-100' }}">
                        <x-gicon name="settings" class="text-[20px]" />
                        <span class="sidebar-label">Panel de control</span>
                    </a>

                    <a href="{{ route('web.games.create') }}" title="Añadir Juego"
                        class="flex items-center justify-center gap-2 w-full bg-indigo-600 hover:bg-indigo-500 text-white px-3 py-2.5 rounded-lg text-sm font-medium transition-colors mt-2">
                        <x-gicon name="add_circle" class="text-[18px]" />
                        <span class="sidebar-label">Añadir Juego</span>
                    </a>
                </div>
            </aside>

            <main class="flex-1 overflow-y-auto bg-slate-950 px-4 py-6 md:px-8 md:py-8">
                @yield('content')
            </main>

        </div>

    </div>

    <!-- Toasts: contenedor donde showToast() (resources/js/app.js) inserta los avisos.
         top-16 (no top-4) porque el header (h-12 = 3rem) es position:static, no fixed:
         con top-4 el contenedor fixed quedaba superpuesto encima de la cabecera (logo,
         buscador, tema, perfil), tapando esos iconos en vez de aparecer debajo -->
    <div id="toast-container" class="fixed top-16 right-4 z-50 flex flex-col gap-2 w-[calc(100%-2rem)] max-w-sm pointer-events-none"></div>

    <!-- Confirmación de acciones destructivas: un único <dialog> reutilizado por
         cualquier formulario con class="js-confirm-delete" (ver app.js) en vez
         del confirm() nativo del navegador, que no respeta el tema de la app. -->
    <dialog id="confirm-dialog" class="rounded-xl border border-slate-800 bg-slate-900 text-slate-100 p-0 backdrop:bg-black/60 w-full max-w-sm">
        <div class="p-5">
            <div class="flex items-start gap-3">
                <div class="flex-shrink-0 w-10 h-10 rounded-full bg-red-500/10 flex items-center justify-center">
                    <x-gicon name="warning" class="text-[22px] text-red-400" />
                </div>
                <div class="flex-1 min-w-0 pt-1.5">
                    <h2 id="confirm-dialog-title" class="text-base font-semibold text-slate-100">¿Seguro?</h2>
                    <p id="confirm-dialog-message" class="text-sm text-slate-400 mt-1"></p>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 mt-5">
                <button type="button" id="confirm-dialog-cancel" class="text-slate-400 hover:text-slate-100 text-sm font-medium px-4 py-2">Cancelar</button>
                <button type="button" id="confirm-dialog-accept" class="bg-red-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-red-500 transition-colors">Confirmar</button>
            </div>
        </div>
    </dialog>

    <!-- Búsqueda rápida (Ctrl+K / Cmd+K, también "/" y el buscador de la
         colección vía .js-quick-search-trigger): resultados en vivo por
         título/EAN con filtros opcionales de plataforma/estado, ver
         initQuickSearch en app.js. Es el único buscador de texto de la app:
         la colección (games/_filters.blade.php) ya no tiene su propio input,
         solo un botón que abre este mismo diálogo. Los resultados son
         enlaces normales a la ficha del juego, sin navegación por JS. -->
    <dialog id="quick-search-dialog" data-url="{{ route('web.search.quick') }}"
        class="rounded-xl border border-slate-800 bg-slate-900 text-slate-100 p-0 backdrop:bg-black/60 w-full max-w-lg">
        <div class="flex items-center gap-3 px-4 py-3 border-b border-slate-800">
            <x-gicon name="search" class="text-[20px] text-slate-500 flex-shrink-0" />
            <input type="text" id="quick-search-input" autocomplete="off" spellcheck="false"
                class="flex-1 min-w-0 bg-transparent text-sm text-slate-100 placeholder:text-slate-500 focus:outline-none"
                placeholder="Buscar por título o EAN…">
            <button type="button" id="barcode-scan-trigger"
                class="flex-shrink-0 flex items-center justify-center w-7 h-7 rounded-lg text-slate-500 hover:bg-slate-800 hover:text-slate-100 transition-colors"
                aria-label="Escanear código de barras" title="Escanear código de barras">
                <x-gicon name="qr_code_scanner" class="text-[18px]" />
            </button>
            <kbd class="flex-shrink-0 text-[10px] font-semibold text-slate-500 border border-slate-700 rounded px-1.5 py-0.5">Esc</kbd>
        </div>

        <!-- Filtros compactos: mismos campos que el panel "Avanzado" de la
             colección (games/_filters.blade.php), para no tener que salir del
             modal a filtrar por plataforma/estado. Ver runSearch en
             initQuickSearch (app.js), que los añade a la query en cada
             búsqueda y en cada 'change'. -->
        <div class="flex items-center gap-2 px-4 py-2 border-b border-slate-800 overflow-x-auto">
            <select id="quick-search-platform" class="flex-shrink-0 rounded-lg border border-slate-700 bg-slate-800 text-slate-300 px-2 py-1.5 text-xs focus:border-indigo-500 focus:ring-indigo-500 outline-none">
                <option value="">Cualquier plataforma</option>
                @foreach($quickSearchPlatforms as $platform)
                    <option value="{{ $platform->id }}">{{ $platform->name }}</option>
                @endforeach
            </select>

            <select id="quick-search-play-status" class="flex-shrink-0 rounded-lg border border-slate-700 bg-slate-800 text-slate-300 px-2 py-1.5 text-xs focus:border-indigo-500 focus:ring-indigo-500 outline-none">
                <option value="">Cualquier estado</option>
                <option value="pending">Pendiente</option>
                <option value="playing">Jugando</option>
                <option value="finished">Terminado</option>
            </select>

            <select id="quick-search-status" class="flex-shrink-0 rounded-lg border border-slate-700 bg-slate-800 text-slate-300 px-2 py-1.5 text-xs focus:border-indigo-500 focus:ring-indigo-500 outline-none">
                <option value="">Cualquier propiedad</option>
                <option value="owned">En colección</option>
                <option value="sold">Vendido</option>
            </select>
        </div>

        <div id="quick-search-results" class="max-h-[60vh] overflow-y-auto"></div>
    </dialog>

    <!-- Escaneo de código de barras (EAN) con la cámara, ver initBarcodeScanner
         en app.js: al detectar un código lo vuelca en el buscador rápido de
         arriba, que ya sabe proponer dar de alta el juego si no hay ninguna
         coincidencia. Se abre encima del diálogo de búsqueda (dos <dialog>
         apilados es válido). -->
    <dialog id="barcode-scan-dialog" class="rounded-xl border border-slate-800 bg-slate-900 text-slate-100 p-0 backdrop:bg-black/70 w-full max-w-sm">
        <div class="p-4">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-sm font-semibold text-slate-100 flex items-center gap-1.5">
                    <x-gicon name="qr_code_scanner" class="text-[18px]" />
                    Escanear código de barras
                </h2>
                <button type="button" id="barcode-scan-cancel"
                    class="flex items-center justify-center w-7 h-7 rounded-lg text-slate-400 hover:bg-slate-800 hover:text-slate-100 transition-colors"
                    aria-label="Cancelar">
                    <x-gicon name="close" class="text-[18px]" />
                </button>
            </div>

            <video id="barcode-scan-video" class="w-full aspect-video rounded-lg bg-black object-cover" muted playsinline></video>

            <p id="barcode-scan-error" class="hidden mt-3 text-sm text-red-400"></p>
            <p class="mt-3 text-xs text-slate-500 text-center">Apunta la cámara al código de barras (EAN) de la caja del juego.</p>
        </div>
    </dialog>

    @if(session('success'))
        <script nonce="{{ $cspNonce }}">
            document.addEventListener('DOMContentLoaded', () => window.showToast?.(@json(session('success')), 'success', @json(session('undoUrl'))));
        </script>
    @endif

    @if(session('error'))
        <script nonce="{{ $cspNonce }}">
            document.addEventListener('DOMContentLoaded', () => window.showToast?.(@json(session('error')), 'error'));
        </script>
    @endif

</body>
</html>
