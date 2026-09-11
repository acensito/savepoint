@extends('layouts.app')

@section('content')
    @php
        $input = 'w-full rounded-lg border border-slate-700 bg-slate-800 text-slate-100 px-4 py-2 focus:border-indigo-500 focus:ring-indigo-500 outline-hidden';
        $label = 'block font-medium text-sm text-slate-300 mb-1';
        $toggleUrl = route('web.panel.settings.toggles');
    @endphp

    <div class="max-w-2xl mx-auto py-6">
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-slate-100 tracking-tight">Ajustes</h1>
            <p class="text-slate-400 mt-1">Comportamiento de la app para tu cuenta.</p>
        </div>

        <!-- Un único formulario para todas las tarjetas: son ajustes de
             preferencia sin nada sensible (a diferencia de profile/edit.blade.php,
             que separa datos de cuenta y contraseña), así que un solo "Guardar"
             es más cómodo que uno por tarjeta. Agrupadas en secciones (mismo
             patrón visual que panel/index.blade.php) en vez de una pila plana:
             la de Seguridad quedaba antes metida sin más entre las dos de
             IGDB, sin relación entre ellas. -->
        <form action="{{ route('web.panel.settings.update') }}" method="POST" class="space-y-10">
            @csrf
            @method('PUT')

            <div>
                <h2 class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-3">Secciones</h2>
                <div class="space-y-6">
                    <div class="bg-slate-900 border border-slate-800 rounded-xl p-8">
                        <h3 class="text-lg font-semibold text-slate-100 mb-1">Secciones opcionales</h3>
                        <p class="text-sm text-slate-500 mb-6">
                            Oculta del menú lateral las secciones que no uses. Los datos que ya tuvieran no se borran
                            ni cambian: si reactivas una sección, todo sigue donde estaba.
                        </p>

                        <div class="space-y-4">
                            <x-toggle name="section_wishlist_enabled" :checked="$user->section_wishlist_enabled"
                                      :url="$toggleUrl">
                                Lista de deseos
                            </x-toggle>
                            <x-toggle name="section_commissions_enabled" :checked="$user->section_commissions_enabled"
                                      :url="$toggleUrl">
                                Encargos
                            </x-toggle>
                            <x-toggle name="section_for_sale_enabled" :checked="$user->section_for_sale_enabled"
                                      :url="$toggleUrl">
                                En venta
                            </x-toggle>
                            <x-toggle name="section_sales_enabled" :checked="$user->section_sales_enabled"
                                      :url="$toggleUrl">
                                Ventas
                            </x-toggle>
                            <x-toggle name="section_stats_enabled" :checked="$user->section_stats_enabled"
                                      :url="$toggleUrl">
                                Estadísticas
                            </x-toggle>
                        </div>
                    </div>
                </div>
            </div>

            <div>
                <h2 class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-3">IGDB</h2>
                <div class="space-y-6">
                    <div class="bg-slate-900 border border-slate-800 rounded-xl p-8">
                        <h3 class="text-lg font-semibold text-slate-100 mb-1">Credenciales</h3>
                        <p class="text-sm text-slate-500 mb-6">
                            Autocompleta desarrollador, fecha de lanzamiento, géneros y nota al dar de alta o abrir
                            un juego, y permite elegir un fondo con arte oficial. Cada cuenta usa sus propias
                            credenciales: date de alta gratis como desarrollador de Twitch en
                            <a href="https://dev.twitch.tv/console/apps" target="_blank" rel="noopener"
                               class="text-indigo-400 hover:underline">dev.twitch.tv/console/apps</a>
                            ("Register Your Application", cualquier OAuth Redirect URL vale, p. ej. https://localhost) y
                            copia aquí el Client ID y el Client Secret que te dé. Sin ellas, esta app nunca envía nada a
                            IGDB.
                        </p>

                        <x-toggle name="igdb_enabled" :checked="$user->igdb_enabled" :url="$toggleUrl" class="mb-4">
                            Permitir el uso de IGDB con mis credenciales
                        </x-toggle>

                        <div id="igdb-credentials"
                             class="grid grid-cols-1 sm:grid-cols-2 gap-4 {{ $user->igdb_enabled ? '' : 'hidden' }}">
                            <div>
                                <label for="igdb_client_id" class="{{ $label }}">Client ID</label>
                                <input type="text" name="igdb_client_id" id="igdb_client_id" autocomplete="off"
                                       value="{{ old('igdb_client_id', $user->igdb_client_id) }}" class="{{ $input }}">
                            </div>
                            <div>
                                <label for="igdb_client_secret" class="{{ $label }}">Client Secret</label>
                                <input type="password" name="igdb_client_secret" id="igdb_client_secret"
                                       autocomplete="off"
                                       placeholder="{{ $user->igdb_client_secret ? '•••••••••••••• (sin cambios)' : '' }}"
                                       class="{{ $input }}">
                            </div>
                        </div>
                    </div>

                    <div class="bg-slate-900 border border-slate-800 rounded-xl p-8">
                        <h3 class="text-lg font-semibold text-slate-100 mb-1">Fondo automático</h3>
                        <p class="text-sm text-slate-500 mb-6">
                            Requiere IGDB activado arriba. Al dar de alta un juego, se intentará identificarlo en
                            IGDB y, si tiene arte disponible, se pondrá el primero como fondo de la ficha
                            automáticamente. Podrás cambiarlo a mano en cualquier momento entre el resto de fondos
                            disponibles, igual que hasta ahora. Si lo desactivas, el fondo se queda vacío hasta que
                            lo elijas tú.
                        </p>

                        <x-toggle name="auto_igdb_background" :checked="$user->auto_igdb_background" :url="$toggleUrl">
                            Establecer el fondo automáticamente al dar de alta un juego
                        </x-toggle>
                    </div>
                </div>
            </div>

            <div>
                <h2 class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-3">Cuenta</h2>
                <div class="space-y-6">
                    <div class="bg-slate-900 border border-slate-800 rounded-xl p-8">
                        <h3 class="text-lg font-semibold text-slate-100 mb-1">Verificación en dos pasos</h3>
                        <p class="text-sm text-slate-500 mb-6">
                            Activado, cada inicio de sesión desde un dispositivo que no hayas marcado como "de
                            confianza" pedirá además un código de un solo uso enviado a tu email.
                        </p>

                        <x-toggle name="two_factor_enabled" :checked="$user->two_factor_enabled" :url="$toggleUrl">
                            Pedir un código por email al iniciar sesión
                        </x-toggle>
                    </div>

                    <div class="bg-slate-900 border border-slate-800 rounded-xl p-8">
                        <h3 class="text-lg font-semibold text-slate-100 mb-1">Dispositivos de confianza</h3>
                        <p class="text-sm text-slate-500 mb-6">
                            Al marcar "confiar en este dispositivo" en el código de dos pasos, ese navegador deja de
                            pedirlo durante 30 días. Se revocan solos al cambiar la contraseña o desactivar la
                            verificación en dos pasos, pero también puedes revocarlos aquí a mano en cualquier momento
                            — por ejemplo, si prestaste el equipo o ya no confías en él.
                        </p>

                        {{-- Los botones de abajo usan form="..." en vez de envolverse en su
                             propio <form>: esta tarjeta vive dentro del <form> grande de
                             "Guardar ajustes" (ver comentario al principio del fichero), y un
                             <form> anidado dentro de otro es HTML inválido — el navegador
                             ignora la apertura del <form> interior pero, al llegar a su
                             </form>, cierra ahí mismo el <form> exterior (el de verdad), dejando
                             fuera de él todo lo que viniera después: los colores de navbar, las
                             preferencias de Colección y el propio botón "Guardar ajustes", que
                             deja de estar asociado a ningún formulario y no hace nada al
                             pulsarlo. Los <form> reales de este bloque se declaran más abajo,
                             ya fuera del <form> grande (ver el cierre de este fichero). --}}
                        @if($trustedDevices->isEmpty())
                            <p class="text-sm text-slate-500">No tienes ningún dispositivo marcado como de confianza ahora mismo.</p>
                        @else
                            <div class="space-y-2">
                                @foreach($trustedDevices as $device)
                                    <div class="flex items-center justify-between gap-3 py-2 border-b border-slate-800 last:border-0">
                                        <div class="min-w-0">
                                            <p class="text-sm text-slate-200 truncate">{{ $device->user_agent ?: 'Navegador desconocido' }}</p>
                                            <p class="text-xs text-slate-500">
                                                {{ $device->ip_address ?? 'IP desconocida' }}
                                                · confiado el {{ $device->created_at->format('d/m/Y') }}
                                                · caduca el {{ $device->expires_at->format('d/m/Y') }}
                                            </p>
                                        </div>
                                        <button type="submit" form="revoke-device-{{ $device->id }}" class="shrink-0 text-xs font-medium text-red-400 hover:text-red-300">Revocar</button>
                                    </div>
                                @endforeach
                            </div>

                            <button type="submit" form="revoke-all-devices" class="mt-4 text-sm font-medium text-red-400 hover:text-red-300">Revocar todos los dispositivos</button>
                        @endif
                    </div>

                    <div class="bg-slate-900 border border-slate-800 rounded-xl p-8">
                        <h3 class="text-lg font-semibold text-slate-100 mb-1">Apariencia</h3>
                        <p class="text-sm text-slate-500 mb-6">
                            Tema de la aplicación y color de la barra de navegación superior. En modo automático se
                            sigue el tema de tu navegador (normalmente heredado del sistema operativo, pero puede
                            diferir si el navegador tiene su propio ajuste independiente).
                        </p>

                        <div class="flex flex-wrap gap-3 mb-6">
                            <label
                                class="flex items-center gap-2 cursor-pointer rounded-lg border border-slate-700 px-3 py-2 text-sm text-slate-300 has-checked:border-indigo-500">
                                <input type="radio" name="theme" value="dark" class="js-theme-radio accent-indigo-500"
                                    {{ old('theme', $user->theme) === 'dark' ? 'checked' : '' }}>
                                <x-gicon name="dark_mode" class="text-[18px]"/>
                                Oscuro
                            </label>
                            <label
                                class="flex items-center gap-2 cursor-pointer rounded-lg border border-slate-700 px-3 py-2 text-sm text-slate-300 has-checked:border-indigo-500">
                                <input type="radio" name="theme" value="light" class="js-theme-radio accent-indigo-500"
                                    {{ old('theme', $user->theme) === 'light' ? 'checked' : '' }}>
                                <x-gicon name="light_mode" class="text-[18px]"/>
                                Claro
                            </label>
                            <label
                                class="flex items-center gap-2 cursor-pointer rounded-lg border border-slate-700 px-3 py-2 text-sm text-slate-300 has-checked:border-indigo-500">
                                <input type="radio" name="theme" value="auto" class="js-theme-radio accent-indigo-500"
                                    {{ old('theme', $user->theme) === 'auto' ? 'checked' : '' }}>
                                <x-gicon name="brightness_auto" class="text-[18px]"/>
                                Automático
                            </label>
                        </div>

                        <div class="flex flex-wrap gap-3">
                            @foreach(\App\Http\Controllers\Web\PanelController::NAVBAR_COLORS as $value => $hex)
                                <label class="relative cursor-pointer">
                                    <input type="radio" name="navbar_color" value="{{ $value }}" class="sr-only peer"
                                        {{ old('navbar_color', $user->navbar_color) === $value ? 'checked' : '' }}>
                                    <span
                                        class="flex items-center justify-center w-9 h-9 rounded-full border-2 border-transparent peer-checked:border-slate-100 peer-focus-visible:ring-2 peer-focus-visible:ring-offset-2 peer-focus-visible:ring-offset-slate-900 peer-focus-visible:ring-indigo-500 transition-colors"
                                        style="background-color: {{ $hex }}" title="{{ ucfirst($value) }}"></span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <div>
                <h2 class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-3">Colección</h2>
                <div class="space-y-6">
                    <div class="bg-slate-900 border border-slate-800 rounded-xl p-8">
                        <h3 class="text-lg font-semibold text-slate-100 mb-1">Preferencias de la colección</h3>
                        <p class="text-sm text-slate-500 mb-6">
                            Con qué orden y tamaño de página arranca "Mi Colección" cuando no lo cambias a mano en el
                            momento (elegir un filtro ahí sigue ganando siempre a esto).
                        </p>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div>
                                <label for="default_sort" class="{{ $label }}">Orden por defecto</label>
                                <select name="default_sort" id="default_sort" class="{{ $input }}">
                                    <option value="">Más recientes primero</option>
                                    <option
                                        value="title" {{ old('default_sort', $user->default_sort) === 'title' ? 'selected' : '' }}>
                                        Título
                                    </option>
                                    <option
                                        value="price_paid" {{ old('default_sort', $user->default_sort) === 'price_paid' ? 'selected' : '' }}>
                                        Precio
                                    </option>
                                    <option
                                        value="rating" {{ old('default_sort', $user->default_sort) === 'rating' ? 'selected' : '' }}>
                                        Conservación
                                    </option>
                                    <option
                                        value="purchase_date" {{ old('default_sort', $user->default_sort) === 'purchase_date' ? 'selected' : '' }}>
                                        Fecha de compra
                                    </option>
                                </select>
                            </div>
                            <div>
                                <label for="default_dir" class="{{ $label }}">Dirección</label>
                                <select name="default_dir" id="default_dir" class="{{ $input }}">
                                    <option
                                        value="desc" {{ old('default_dir', $user->default_dir) === 'desc' ? 'selected' : '' }}>
                                        Descendente
                                    </option>
                                    <option
                                        value="asc" {{ old('default_dir', $user->default_dir) === 'asc' ? 'selected' : '' }}>
                                        Ascendente
                                    </option>
                                </select>
                            </div>
                            <div>
                                <label for="default_per_page" class="{{ $label }}">Por página</label>
                                <select name="default_per_page" id="default_per_page" class="{{ $input }}">
                                    @foreach([10, 20, 50, 100] as $option)
                                        <option
                                            value="{{ $option }}" {{ (int) old('default_per_page', $user->default_per_page) === $option ? 'selected' : '' }}>{{ $option }}
                                            por página
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="bg-slate-900 border border-slate-800 rounded-xl p-8">
                        <h3 class="text-lg font-semibold text-slate-100 mb-1">Aviso de mal estado</h3>
                        <p class="text-sm text-slate-500 mb-6">
                            Resalta en amarillo, en fila de tabla, tarjeta y estantería, los juegos con Conservación de 2 estrellas o menos (Malo/Regular), para detectarlos de un vistazo sin filtrar ni ordenar.
                        </p>

                        <x-toggle name="highlight_low_rating" :checked="$user->highlight_low_rating" :url="$toggleUrl">
                            Resaltar los juegos en mal estado
                        </x-toggle>
                    </div>

                    <div class="bg-slate-900 border border-slate-800 rounded-xl p-8">
                        <h3 class="text-lg font-semibold text-slate-100 mb-1">Valores por defecto al dar de alta</h3>
                        <p class="text-sm text-slate-500 mb-6">
                            Qué región y edición se preseleccionan al añadir un juego nuevo. Se pueden cambiar a mano
                            en cada alta, igual que hasta ahora.
                        </p>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="default_edition_id" class="{{ $label }}">Edición</label>
                                <select name="default_edition_id" id="default_edition_id" class="{{ $input }}">
                                    <option value="">Ninguna</option>
                                    @foreach($editions as $edition)
                                        <option
                                            value="{{ $edition->id }}" {{ (string) old('default_edition_id', $user->default_edition_id) === (string) $edition->id ? 'selected' : '' }}>
                                            {{ $edition->name }} · {{ $edition->formatLabel() }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="default_region" class="{{ $label }}">Región</label>
                                <select name="default_region" id="default_region" class="{{ $input }}">
                                    <option value="">Sin especificar</option>
                                    @foreach(\App\Http\Controllers\Web\GameController::REGION_PRESETS as $preset)
                                        <option
                                            value="{{ $preset }}" {{ old('default_region', $user->default_region) === $preset ? 'selected' : '' }}>{{ $preset }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="bg-slate-900 border border-slate-800 rounded-xl p-8">
                        <h3 class="text-lg font-semibold text-slate-100 mb-1">Búsqueda rápida</h3>
                        <p class="text-sm text-slate-500 mb-6">
                            Si tu lista de deseos aparece mezclada con tu colección en los resultados de Ctrl+K.
                        </p>

                        <x-toggle name="quick_search_exclude_wishlist" :checked="$user->quick_search_exclude_wishlist"
                                  :url="$toggleUrl">
                            Excluir la lista de deseos de los resultados de Ctrl+K
                        </x-toggle>
                    </div>

                    <div class="bg-slate-900 border border-slate-800 rounded-xl p-8">
                        <h3 class="text-lg font-semibold text-slate-100 mb-1">Juegos en venta</h3>
                        <p class="text-sm text-slate-500 mb-6">
                            Los juegos marcados "en venta" tienen su propia sección (<a
                                href="{{ route('web.for-sale.index') }}" class="text-indigo-400 hover:underline">En
                                venta</a>, en el menú lateral) para darles mantenimiento sin mezclarlos con el resto.
                            Siguen viéndose ahí y filtrando la colección aunque actives esto.
                        </p>

                        <x-toggle name="hide_for_sale_from_collection" :checked="$user->hide_for_sale_from_collection"
                                  :url="$toggleUrl">
                            Ocultar los juegos en venta del listado de mi colección
                        </x-toggle>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-end">
                <button type="submit"
                        class="bg-(--color-navbar) text-white px-5 py-2.5 rounded-lg text-sm font-medium hover:bg-(--color-navbar-hover) transition-colors">
                    Guardar ajustes
                </button>
            </div>
        </form>

        {{-- Formularios reales de "Dispositivos de confianza" (ver comentario en esa
             tarjeta más arriba): fuera del <form> grande a propósito, referenciados por
             los botones "Revocar"/"Revocar todos" con form="...". --}}
        @foreach($trustedDevices as $device)
            <form id="revoke-device-{{ $device->id }}" method="POST"
                  action="{{ route('web.panel.settings.trusted-devices.revoke', $device) }}" class="hidden">
                @csrf
                @method('DELETE')
            </form>
        @endforeach
        @if($trustedDevices->isNotEmpty())
            <form id="revoke-all-devices" method="POST"
                  action="{{ route('web.panel.settings.trusted-devices.revoke-all') }}" class="hidden">
                @csrf
                @method('DELETE')
            </form>
        @endif
    </div>

    <script nonce="{{ $cspNonce }}">
        document.getElementById('igdb_enabled')?.addEventListener('change', function () {
            document.getElementById('igdb-credentials').classList.toggle('hidden', !this.checked);
        });
    </script>
@endsection
