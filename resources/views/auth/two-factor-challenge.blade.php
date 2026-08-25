<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificación en dos pasos - SavePoint</title>
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
    <link href="https://fonts.googleapis.com/css2?family=Instrument+Sans:wght@400;500;600&display=swap"
          rel="stylesheet">
    @include('partials.material-symbols-link')
    <script nonce="{{ $cspNonce }}">
        (function () {
            try {
                if (localStorage.getItem('sp:theme') === 'light') {
                    document.documentElement.classList.add('light');
                }
            } catch (e) {
            }
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-950 text-slate-300 antialiased min-h-screen flex items-center justify-center px-4">

<button type="button"
        class="js-theme-toggle fixed top-4 right-4 flex items-center justify-center w-9 h-9 rounded-lg text-slate-400 hover:bg-slate-900 hover:text-slate-100 transition-colors"
        aria-label="Cambiar tema">
    <x-gicon name="light_mode" class="text-[20px]"/>
</button>

<div class="w-full max-w-sm">
    <div class="flex items-center justify-center gap-2 mb-8">
        <x-gicon name="joystick" class="text-[32px] text-indigo-400"/>
        <span class="text-xl font-bold tracking-tight text-slate-100">SavePoint</span>
    </div>

    <div class="bg-slate-900 border border-slate-800 rounded-xl p-8">
        <h1 class="text-lg font-bold text-slate-100 mb-1">Verificación en dos pasos</h1>
        <p class="text-slate-400 text-sm mb-6">
            Te hemos enviado un código a <strong class="text-slate-300">{{ $email }}</strong>. Caduca en 10 minutos.
        </p>

        @if (session('success'))
            <div
                class="mb-4 text-sm text-emerald-400 bg-emerald-500/10 border border-emerald-500/20 rounded-lg px-4 py-2">
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div class="mb-4 text-sm text-red-400 bg-red-500/10 border border-red-500/20 rounded-lg px-4 py-2">
                {{ session('error') }}
            </div>
        @endif

        @error('code')
        <div class="mb-4 text-sm text-red-400 bg-red-500/10 border border-red-500/20 rounded-lg px-4 py-2">
            {{ $message }}
        </div>
        @enderror

        <form action="{{ route('two-factor.verify') }}" method="POST" class="space-y-5">
            @csrf

            <div>
                <label for="code" class="block font-medium text-sm text-slate-300 mb-1">Código</label>
                <input type="text" name="code" id="code" inputmode="numeric" pattern="[0-9]*"
                       autocomplete="one-time-code"
                       maxlength="6" required autofocus
                       class="w-full rounded-lg border border-slate-700 bg-slate-800 text-slate-100 px-4 py-2 tracking-[0.5em] text-center text-lg focus:border-indigo-500 focus:ring-indigo-500 outline-none">
            </div>

            <label class="flex items-center gap-2 text-sm text-slate-400">
                <input type="checkbox" name="trust_device" value="1"
                       class="rounded border-slate-600 bg-slate-800 text-indigo-600 focus:ring-indigo-500">
                Recordar este dispositivo 30 días
            </label>

            <button type="submit"
                    class="w-full bg-indigo-600 text-white px-4 py-2.5 rounded-lg text-sm font-medium hover:bg-indigo-500 transition-colors">
                Verificar
            </button>
        </form>

        <form action="{{ route('two-factor.resend') }}" method="POST" class="mt-4">
            @csrf
            <button type="submit"
                    class="w-full text-center text-sm text-indigo-400 hover:text-indigo-300 transition-colors">
                Reenviar código
            </button>
        </form>
    </div>
</div>

</body>
</html>
