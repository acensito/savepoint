<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SavePoint - Mi Colección</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Instrument+Sans:wght@400;500;600&family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-950 text-slate-300 antialiased">

    <div class="flex h-screen overflow-hidden">

        <!-- Sidebar -->
        <aside class="w-60 flex-shrink-0 bg-slate-900 border-r border-slate-800 flex flex-col">
            <a href="{{ route('web.games.index') }}" class="flex items-center gap-2 px-5 py-5 border-b border-slate-800">
                <x-gicon name="extension" class="text-[26px] text-indigo-400" />
                <span class="text-lg font-bold tracking-tight text-slate-100">SavePoint</span>
            </a>

            <nav class="flex-1 px-3 py-4 space-y-1">
                <a href="{{ route('web.games.index') }}"
                    class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors {{ request()->routeIs('web.games.*') ? 'bg-indigo-500/10 text-indigo-300' : 'text-slate-400 hover:bg-slate-800 hover:text-slate-100' }}">
                    <x-gicon name="sports_esports" class="text-[20px]" />
                    Colección
                </a>

                <a href="{{ route('web.platforms.index') }}"
                    class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors {{ request()->routeIs('web.platforms.*') ? 'bg-indigo-500/10 text-indigo-300' : 'text-slate-400 hover:bg-slate-800 hover:text-slate-100' }}">
                    <x-gicon name="memory" class="text-[20px]" />
                    Plataformas
                </a>

                <a href="{{ route('web.manufacturers.index') }}"
                    class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors {{ request()->routeIs('web.manufacturers.*') ? 'bg-indigo-500/10 text-indigo-300' : 'text-slate-400 hover:bg-slate-800 hover:text-slate-100' }}">
                    <x-gicon name="storefront" class="text-[20px]" />
                    Fabricantes
                </a>
            </nav>

            <div class="px-3 py-4 border-t border-slate-800">
                <a href="{{ route('web.games.create') }}"
                    class="flex items-center justify-center gap-2 w-full bg-indigo-600 hover:bg-indigo-500 text-white px-3 py-2.5 rounded-lg text-sm font-medium transition-colors">
                    <x-gicon name="add_circle" class="text-[18px]" />
                    Añadir Juego
                </a>
            </div>
        </aside>

        <!-- Columna principal -->
        <div class="flex-1 flex flex-col min-w-0">
            <header class="h-14 flex-shrink-0 bg-slate-900 border-b border-slate-800 px-6 flex items-center justify-end">
                <form action="{{ route('web.logout') }}" method="POST" class="flex items-center gap-4">
                    <span class="text-sm text-slate-400">{{ auth()->user()->email }}</span>
                    @csrf
                    <button type="submit" class="flex items-center gap-1.5 text-sm font-medium text-slate-400 hover:text-red-400 transition-colors">
                        <x-gicon name="logout" class="text-[18px]" />
                        Salir
                    </button>
                </form>
            </header>

            <main class="flex-1 overflow-y-auto px-8 py-8">
                @yield('content')
            </main>
        </div>

    </div>

</body>
</html>
