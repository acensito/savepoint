<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SavePoint - Mi Colección</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-50 text-slate-800 antialiased">

    <nav class="bg-white border-b border-slate-200 px-8 py-4 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            
            <div class="flex items-center gap-2">
                <x-heroicon-o-puzzle-piece class="w-8 h-8 text-indigo-600" />
                <span class="text-xl font-bold tracking-tight text-slate-900">SavePoint</span>
            </div>
            
            <a href="{{ route('web.games.create') }}" class="flex items-center gap-2 text-sm font-medium text-slate-500 hover:text-indigo-600 transition-colors">
                <x-heroicon-o-plus-circle class="w-5 h-5" />
                Añadir Juego
            </a>
            
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-8 py-10">
        @yield('content')
    </main>

</body>
</html>