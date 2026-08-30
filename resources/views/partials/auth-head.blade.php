<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }} - SavePoint</title>
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
        // Bloqueante a propósito: ver el mismo script en layouts/app.blade.php.
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
