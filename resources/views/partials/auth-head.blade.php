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
    {{-- Instrument Sans y Material Symbols autoalojadas (issue #186): ver
         los @font-face en app.css y layouts/app.blade.php. --}}
    <script nonce="{{ $cspNonce }}">
        // Bloqueante a propósito: ver el mismo script en layouts/app.blade.php.
        (function () {
            try {
                var theme = localStorage.getItem('sp:theme');
                if (theme === 'light' || (theme === 'auto' && window.matchMedia && window.matchMedia('(prefers-color-scheme: light)').matches)) {
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

<!-- Apilado por defecto (logo encima del formulario, como en tablet/móvil);
     a partir de lg (1024px, hueco de sobra para las dos columnas sin
     apretarse) pasa a fila, logo a la izquierda y formulario a la derecha.
     Un único contenedor con flex en el propio <div>, no dos anidados: cada
     vista solo tiene que cerrar UNA </div> de más al final (ver el bug de
     hoy con la que auth-head.blade.php cerraba de más) — su tarjeta
     (bg-slate-900...) se convierte sola en el segundo hijo del flex. -->
<div class="w-full max-w-sm lg:max-w-3xl lg:flex lg:items-center lg:gap-16">
    <div class="flex items-center justify-center gap-2 mb-8 lg:mb-0 lg:flex-1 lg:justify-center">
        <x-gicon name="joystick" class="text-[32px] lg:text-[56px] text-indigo-400"/>
        <span class="text-xl lg:text-3xl font-bold tracking-tight text-slate-100">SavePoint</span>
    </div>
    {{-- Sin cerrar a propósito: cada vista (login/register/...) sigue
         dentro de este contenedor con su propio contenido y lo cierra ella
         misma al final (ver el </div> extra antes de </body> en cada una).
         Su tarjeta lleva lg:flex-1 lg:max-w-sm para repartirse el ancho con
         el bloque del logo en la fila de escritorio. --}}
