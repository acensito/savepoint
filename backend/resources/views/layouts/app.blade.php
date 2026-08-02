<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SavePoint - Mi Colección</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Instrument+Sans:wght@400;500;600&family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=swap" rel="stylesheet">
    <script>
        // Bloqueante a propósito: aplica el estado guardado del sidebar y del tema antes
        // del primer pintado para que no haya parpadeo (sidebar expandido/plegado, u
        // oscuro/claro) al cargar o al navegar entre páginas.
        (function () {
            try {
                if (localStorage.getItem('sp:sidebarCollapsed') === '1') {
                    document.documentElement.classList.add('sidebar-collapsed');
                }
                if (localStorage.getItem('sp:theme') === 'light') {
                    document.documentElement.classList.add('light');
                }
                if (localStorage.getItem('sp:gamesView') === 'grid') {
                    document.documentElement.classList.add('games-grid-view');
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
                <button type="button" class="js-theme-toggle flex items-center justify-center w-8 h-8 rounded-lg text-indigo-100 hover:bg-white/10 hover:text-white transition-colors"
                    aria-label="Cambiar tema">
                    <x-gicon name="light_mode" class="text-[20px]" />
                </button>

                <form action="{{ route('web.logout') }}" method="POST" class="flex items-center gap-4">
                    <a href="{{ route('web.profile.edit') }}" class="flex items-center gap-1.5 text-sm text-indigo-100 hover:text-white transition-colors {{ request()->routeIs('web.profile.*') ? 'text-white font-medium' : '' }}">
                        <x-gicon name="account_circle" class="text-[18px]" />
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

                    <a href="{{ route('web.stats.index') }}" title="Estadísticas"
                        class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors {{ request()->routeIs('web.stats.*') ? 'bg-indigo-500/10 text-indigo-300' : 'text-slate-400 hover:bg-slate-800 hover:text-slate-100' }}">
                        <x-gicon name="bar_chart" class="text-[20px]" />
                        <span class="sidebar-label">Estadísticas</span>
                    </a>

                    <a href="{{ route('web.platforms.index') }}" title="Plataformas"
                        class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors {{ request()->routeIs('web.platforms.*') ? 'bg-indigo-500/10 text-indigo-300' : 'text-slate-400 hover:bg-slate-800 hover:text-slate-100' }}">
                        <x-gicon name="memory" class="text-[20px]" />
                        <span class="sidebar-label">Plataformas</span>
                    </a>

                    <a href="{{ route('web.editions.index') }}" title="Ediciones"
                        class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors {{ request()->routeIs('web.editions.*') ? 'bg-indigo-500/10 text-indigo-300' : 'text-slate-400 hover:bg-slate-800 hover:text-slate-100' }}">
                        <x-gicon name="workspace_premium" class="text-[20px]" />
                        <span class="sidebar-label">Ediciones</span>
                    </a>

                    <a href="{{ route('web.manufacturers.index') }}" title="Fabricantes"
                        class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors {{ request()->routeIs('web.manufacturers.*') ? 'bg-indigo-500/10 text-indigo-300' : 'text-slate-400 hover:bg-slate-800 hover:text-slate-100' }}">
                        <x-gicon name="storefront" class="text-[20px]" />
                        <span class="sidebar-label">Fabricantes</span>
                    </a>
                </nav>

                <div class="px-3 py-4 border-t border-slate-800 space-y-1">
                    <a href="{{ route('web.games.import') }}" title="Importar colección"
                        class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors {{ request()->routeIs('web.games.import*') ? 'bg-indigo-500/10 text-indigo-300' : 'text-slate-400 hover:bg-slate-800 hover:text-slate-100' }}">
                        <x-gicon name="upload_file" class="text-[20px]" />
                        <span class="sidebar-label">Importar</span>
                    </a>

                    <a href="{{ route('web.games.trash') }}" title="Papelera de reciclaje"
                        class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors {{ request()->routeIs('web.games.trash') ? 'bg-indigo-500/10 text-indigo-300' : 'text-slate-400 hover:bg-slate-800 hover:text-slate-100' }}">
                        <x-gicon name="delete" class="text-[20px]" />
                        <span class="sidebar-label">Papelera</span>
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

    <!-- Toasts: contenedor donde showToast() (resources/js/app.js) inserta los avisos -->
    <div id="toast-container" class="fixed top-4 right-4 z-50 flex flex-col gap-2 w-[calc(100%-2rem)] max-w-sm pointer-events-none"></div>

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

    @if(session('success'))
        <script>
            document.addEventListener('DOMContentLoaded', () => window.showToast?.(@json(session('success')), 'success'));
        </script>
    @endif

</body>
</html>
