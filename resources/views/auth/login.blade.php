<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceder - SavePoint</title>
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#4f46e5">
    <link rel="icon" type="image/png" sizes="192x192" href="/icons/icon-192.png">
    <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="SavePoint">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Instrument+Sans:wght@400;500;600&family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=swap" rel="stylesheet">
    <script>
        // Bloqueante a propósito: ver el mismo script en layouts/app.blade.php.
        (function () {
            try {
                if (localStorage.getItem('sp:theme') === 'light') {
                    document.documentElement.classList.add('light');
                }
            } catch (e) {}
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-950 text-slate-300 antialiased min-h-screen flex items-center justify-center px-4">

    <button type="button" class="js-theme-toggle fixed top-4 right-4 flex items-center justify-center w-9 h-9 rounded-lg text-slate-400 hover:bg-slate-900 hover:text-slate-100 transition-colors"
        aria-label="Cambiar tema">
        <x-gicon name="light_mode" class="text-[20px]" />
    </button>

    <div class="w-full max-w-sm">
        <div class="flex items-center justify-center gap-2 mb-8">
            <x-gicon name="joystick" class="text-[32px] text-indigo-400" />
            <span class="text-xl font-bold tracking-tight text-slate-100">SavePoint</span>
        </div>

        <div class="bg-slate-900 border border-slate-800 rounded-xl p-8">
            <h1 class="text-lg font-bold text-slate-100 mb-1">Acceder</h1>
            <p class="text-slate-400 text-sm mb-6">Entra con tu cuenta para ver tu colección.</p>

            @error('email')
                <div class="mb-4 text-sm text-red-400 bg-red-500/10 border border-red-500/20 rounded-lg px-4 py-2">
                    {{ $message }}
                </div>
            @enderror

            <form action="{{ route('web.login.attempt') }}" method="POST" class="space-y-5">
                @csrf

                <div>
                    <label for="email" class="block font-medium text-sm text-slate-300 mb-1">Email</label>
                    <input type="email" name="email" id="email" value="{{ old('email') }}" required autofocus
                        class="w-full rounded-lg border border-slate-700 bg-slate-800 text-slate-100 px-4 py-2 focus:border-indigo-500 focus:ring-indigo-500 outline-none">
                </div>

                <div>
                    <label for="password" class="block font-medium text-sm text-slate-300 mb-1">Contraseña</label>
                    <input type="password" name="password" id="password" required
                        class="w-full rounded-lg border border-slate-700 bg-slate-800 text-slate-100 px-4 py-2 focus:border-indigo-500 focus:ring-indigo-500 outline-none">
                </div>

                <div class="flex items-center justify-between">
                    <label class="flex items-center gap-2 text-sm text-slate-400">
                        <input type="checkbox" name="remember" class="rounded border-slate-600 bg-slate-800 text-indigo-600 focus:ring-indigo-500">
                        Recordarme
                    </label>

                    <a href="{{ route('password.request') }}" class="text-sm text-indigo-400 hover:text-indigo-300 transition-colors">
                        ¿Olvidaste tu contraseña?
                    </a>
                </div>

                <button type="submit" class="w-full bg-indigo-600 text-white px-4 py-2.5 rounded-lg text-sm font-medium hover:bg-indigo-500 transition-colors">
                    Entrar
                </button>
            </form>
        </div>
    </div>

</body>
</html>
