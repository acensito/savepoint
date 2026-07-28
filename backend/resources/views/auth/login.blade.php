<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceder - SavePoint</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Instrument+Sans:wght@400;500;600&family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-950 text-slate-300 antialiased min-h-screen flex items-center justify-center px-4">

    <div class="w-full max-w-sm">
        <div class="flex items-center justify-center gap-2 mb-8">
            <x-gicon name="extension" class="text-[32px] text-indigo-400" />
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

                <label class="flex items-center gap-2 text-sm text-slate-400">
                    <input type="checkbox" name="remember" class="rounded border-slate-600 bg-slate-800 text-indigo-600 focus:ring-indigo-500">
                    Recordarme
                </label>

                <button type="submit" class="w-full bg-indigo-600 text-white px-4 py-2.5 rounded-lg text-sm font-medium hover:bg-indigo-500 transition-colors">
                    Entrar
                </button>
            </form>
        </div>
    </div>

</body>
</html>
