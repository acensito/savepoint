@extends('layouts.app')

@section('content')
    @php
        $input = 'w-full rounded-lg border border-slate-700 bg-slate-800 text-slate-100 px-4 py-2 focus:border-indigo-500 focus:ring-indigo-500 outline-none';
        $label = 'block font-medium text-sm text-slate-300 mb-1';
    @endphp

    <div class="max-w-2xl mx-auto py-6">
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-slate-100 tracking-tight">Ajustes</h1>
            <p class="text-slate-400 mt-1">Comportamiento de la app para tu cuenta.</p>
        </div>

        <!-- Un único formulario para todas las tarjetas: son ajustes de
             preferencia sin nada sensible (a diferencia de profile/edit.blade.php,
             que separa datos de cuenta y contraseña), así que un solo "Guardar"
             es más cómodo que uno por tarjeta. -->
        <form action="{{ route('web.panel.settings.update') }}" method="POST" class="space-y-6">
            @csrf
            @method('PUT')

            <div class="bg-slate-900 border border-slate-800 rounded-xl p-8">
                <h2 class="text-lg font-semibold text-slate-100 mb-1">IGDB</h2>
                <p class="text-sm text-slate-500 mb-6">
                    Autocompleta desarrollador, fecha de lanzamiento, géneros y nota al dar de alta o abrir un
                    juego, y permite elegir un fondo con arte oficial. Cada cuenta usa sus propias credenciales:
                    date de alta gratis como desarrollador de Twitch en
                    <a href="https://dev.twitch.tv/console/apps" target="_blank" rel="noopener" class="text-indigo-400 hover:underline">dev.twitch.tv/console/apps</a>
                    ("Register Your Application", cualquier OAuth Redirect URL vale, p. ej. https://localhost) y
                    copia aquí el Client ID y el Client Secret que te dé. Sin ellas, esta app nunca envía nada a
                    IGDB.
                </p>

                <label class="flex items-center gap-2 text-sm text-slate-300 mb-4">
                    <input type="checkbox" id="igdb_enabled" name="igdb_enabled" value="1" {{ old('igdb_enabled', $user->igdb_enabled) ? 'checked' : '' }}
                        class="rounded border-slate-600 bg-slate-800 text-indigo-600 focus:ring-indigo-500">
                    Permitir el uso de IGDB con mis credenciales
                </label>

                <div id="igdb-credentials" class="grid grid-cols-1 sm:grid-cols-2 gap-4 {{ $user->igdb_enabled ? '' : 'hidden' }}">
                    <div>
                        <label for="igdb_client_id" class="{{ $label }}">Client ID</label>
                        <input type="text" name="igdb_client_id" id="igdb_client_id" autocomplete="off"
                            value="{{ old('igdb_client_id', $user->igdb_client_id) }}" class="{{ $input }}">
                    </div>
                    <div>
                        <label for="igdb_client_secret" class="{{ $label }}">Client Secret</label>
                        <input type="password" name="igdb_client_secret" id="igdb_client_secret" autocomplete="off"
                            placeholder="{{ $user->igdb_client_secret ? '•••••••••••••• (sin cambios)' : '' }}" class="{{ $input }}">
                    </div>
                </div>
            </div>

            <div class="bg-slate-900 border border-slate-800 rounded-xl p-8">
                <h2 class="text-lg font-semibold text-slate-100 mb-1">Fondo automático desde IGDB</h2>
                <p class="text-sm text-slate-500 mb-6">
                    Requiere IGDB activado arriba. Al dar de alta un juego, se intentará identificarlo en IGDB y, si
                    tiene arte disponible, se pondrá el primero como fondo de la ficha automáticamente. Podrás
                    cambiarlo a mano en cualquier momento entre el resto de fondos disponibles, igual que hasta
                    ahora. Si lo desactivas, el fondo se queda vacío hasta que lo elijas tú.
                </p>

                <label class="flex items-center gap-2 text-sm text-slate-300">
                    <input type="checkbox" name="auto_igdb_background" value="1" {{ old('auto_igdb_background', $user->auto_igdb_background) ? 'checked' : '' }}
                        class="rounded border-slate-600 bg-slate-800 text-indigo-600 focus:ring-indigo-500">
                    Establecer el fondo automáticamente al dar de alta un juego
                </label>
            </div>

            <div class="bg-slate-900 border border-slate-800 rounded-xl p-8">
                <h2 class="text-lg font-semibold text-slate-100 mb-1">Preferencias de la colección</h2>
                <p class="text-sm text-slate-500 mb-6">
                    Con qué orden y tamaño de página arranca "Mi Colección" cuando no lo cambias a mano en el
                    momento (elegir un filtro ahí sigue ganando siempre a esto).
                </p>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label for="default_sort" class="{{ $label }}">Orden por defecto</label>
                        <select name="default_sort" id="default_sort" class="{{ $input }}">
                            <option value="">Más recientes primero</option>
                            <option value="title" {{ old('default_sort', $user->default_sort) === 'title' ? 'selected' : '' }}>Título</option>
                            <option value="price_paid" {{ old('default_sort', $user->default_sort) === 'price_paid' ? 'selected' : '' }}>Precio</option>
                            <option value="rating" {{ old('default_sort', $user->default_sort) === 'rating' ? 'selected' : '' }}>Conservación</option>
                            <option value="purchase_date" {{ old('default_sort', $user->default_sort) === 'purchase_date' ? 'selected' : '' }}>Fecha de compra</option>
                        </select>
                    </div>
                    <div>
                        <label for="default_dir" class="{{ $label }}">Dirección</label>
                        <select name="default_dir" id="default_dir" class="{{ $input }}">
                            <option value="desc" {{ old('default_dir', $user->default_dir) === 'desc' ? 'selected' : '' }}>Descendente</option>
                            <option value="asc" {{ old('default_dir', $user->default_dir) === 'asc' ? 'selected' : '' }}>Ascendente</option>
                        </select>
                    </div>
                    <div>
                        <label for="default_per_page" class="{{ $label }}">Por página</label>
                        <select name="default_per_page" id="default_per_page" class="{{ $input }}">
                            @foreach([10, 20, 50, 100] as $option)
                                <option value="{{ $option }}" {{ (int) old('default_per_page', $user->default_per_page) === $option ? 'selected' : '' }}>{{ $option }} por página</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            <div class="bg-slate-900 border border-slate-800 rounded-xl p-8">
                <h2 class="text-lg font-semibold text-slate-100 mb-1">Valores por defecto al dar de alta</h2>
                <p class="text-sm text-slate-500 mb-6">
                    Qué región y edición se preseleccionan al añadir un juego nuevo. Se pueden cambiar a mano en
                    cada alta, igual que hasta ahora.
                </p>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="default_edition_id" class="{{ $label }}">Edición</label>
                        <select name="default_edition_id" id="default_edition_id" class="{{ $input }}">
                            <option value="">Ninguna</option>
                            @foreach($editions as $edition)
                                <option value="{{ $edition->id }}" {{ (string) old('default_edition_id', $user->default_edition_id) === (string) $edition->id ? 'selected' : '' }}>
                                    {{ $edition->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="default_region" class="{{ $label }}">Región</label>
                        <select name="default_region" id="default_region" class="{{ $input }}">
                            <option value="">Sin especificar</option>
                            @foreach(\App\Http\Controllers\Web\GameController::REGION_PRESETS as $preset)
                                <option value="{{ $preset }}" {{ old('default_region', $user->default_region) === $preset ? 'selected' : '' }}>{{ $preset }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            <div class="bg-slate-900 border border-slate-800 rounded-xl p-8">
                <h2 class="text-lg font-semibold text-slate-100 mb-1">Búsqueda rápida</h2>
                <p class="text-sm text-slate-500 mb-6">
                    Si tu lista de deseos aparece mezclada con tu colección en los resultados de Ctrl+K.
                </p>

                <label class="flex items-center gap-2 text-sm text-slate-300">
                    <input type="checkbox" name="quick_search_exclude_wishlist" value="1" {{ old('quick_search_exclude_wishlist', $user->quick_search_exclude_wishlist) ? 'checked' : '' }}
                        class="rounded border-slate-600 bg-slate-800 text-indigo-600 focus:ring-indigo-500">
                    Excluir la lista de deseos de los resultados de Ctrl+K
                </label>
            </div>

            <div class="bg-slate-900 border border-slate-800 rounded-xl p-8">
                <h2 class="text-lg font-semibold text-slate-100 mb-1">Juegos en venta</h2>
                <p class="text-sm text-slate-500 mb-6">
                    Los juegos marcados "en venta" tienen su propia sección (<a href="{{ route('web.for-sale.index') }}" class="text-indigo-400 hover:underline">En venta</a>, en el menú lateral) para darles mantenimiento sin mezclarlos con el resto. Siguen viéndose ahí y filtrando la colección aunque actives esto.
                </p>

                <label class="flex items-center gap-2 text-sm text-slate-300">
                    <input type="checkbox" name="hide_for_sale_from_collection" value="1" {{ old('hide_for_sale_from_collection', $user->hide_for_sale_from_collection) ? 'checked' : '' }}
                        class="rounded border-slate-600 bg-slate-800 text-indigo-600 focus:ring-indigo-500">
                    Ocultar los juegos en venta del listado de mi colección
                </label>
            </div>

            <div class="flex items-center justify-end">
                <button type="submit" class="bg-indigo-600 text-white px-5 py-2.5 rounded-lg text-sm font-medium hover:bg-indigo-500 transition-colors">
                    Guardar ajustes
                </button>
            </div>
        </form>
    </div>

    <script nonce="{{ $cspNonce }}">
        document.getElementById('igdb_enabled')?.addEventListener('change', function () {
            document.getElementById('igdb-credentials').classList.toggle('hidden', !this.checked);
        });
    </script>
@endsection
