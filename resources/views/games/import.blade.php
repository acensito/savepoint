@extends('layouts.app')

@php
    use Illuminate\Support\Str;

    $importId = session('importId');
    $input = 'w-full rounded-lg border border-slate-700 bg-slate-800 text-slate-100 px-4 py-2 focus:border-indigo-500 focus:ring-indigo-500 outline-hidden';
    $label = 'block font-medium text-sm text-slate-300 mb-1';
@endphp

@section('content')
    <div class="max-w-2xl mx-auto py-6">
        <div class="mb-8 flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-slate-100 tracking-tight">Importar colección</h1>
                <p class="text-slate-400 mt-1">Sube un CSV con tus juegos para darlos de alta de golpe.</p>
            </div>
            <a href="{{ route('web.games.index') }}" class="text-sm font-medium text-slate-400 hover:text-slate-100">
                ← Volver a mi colección
            </a>
        </div>

        @if($importId)
            <!-- La importación se procesa en segundo plano (ver
                 Jobs\ImportGamesFromCsv, GameImportController::store()): este
                 bloque sondea /games/import/status/{id} (ver
                 initImportStatusPolling en app.js) hasta que termina, y
                 entonces pinta el mismo resumen que antes llegaba ya listo en
                 la propia redirección. -->
            <div id="import-status" class="bg-slate-900 border border-slate-800 rounded-xl p-6 mb-6"
                data-status-url="{{ route('web.games.import.status', $importId) }}"
                data-auto-identify-url="{{ route('web.games.auto-identify') }}">
                <div id="import-status-pending" class="flex items-center gap-2 text-slate-300">
                    <x-gicon name="progress_activity" class="text-[20px] animate-spin" />
                    Importando tu colección… puede tardar un poco con ficheros grandes.
                </div>
                <!-- Aviso tras SLOW_IMPORT_WARNING_MS de sondeo sin terminar (ver
                     initImportStatusPolling en app.js, issue #119): una importación
                     normal no debería tardar más de unos minutos, así que pasado ese
                     margen probablemente algo ha ido mal (worker de cola caído...),
                     aunque el sondeo sigue igual por si de verdad solo va lento. -->
                <p id="import-status-slow-warning" class="hidden mt-2 text-sm text-amber-400">
                    Esto está tardando más de lo normal. Puede que algo haya ido mal — prueba a recargar esta página más tarde para comprobar si terminó, o repite la importación si sigue igual.
                </p>
                <div id="import-status-result" class="hidden"></div>
            </div>
        @endif

        <div class="bg-slate-900 border border-slate-800 rounded-xl p-8">
            @error('file')
                <div class="mb-4 text-sm text-red-400 bg-red-500/10 border border-red-500/20 rounded-lg px-4 py-2">
                    {{ $message }}
                </div>
            @enderror

            <p class="text-sm text-slate-400 mb-4">
                Solo el título es obligatorio. Si una plataforma o edición del CSV no existe todavía en el catálogo, se crea automáticamente.
                <a href="{{ route('web.games.import.template') }}" class="text-indigo-400 hover:text-indigo-300">Descargar plantilla de ejemplo</a>.
            </p>

            <form action="{{ route('web.games.import.store') }}" method="POST" enctype="multipart/form-data" class="space-y-5">
                @csrf

                <div>
                    <label for="file" class="block font-medium text-sm text-slate-300 mb-1">Fichero CSV</label>
                    <input type="file" name="file" id="file" accept=".csv,text/csv" required
                        data-preview-url="{{ route('web.games.import.preview') }}"
                        class="block w-full text-sm text-slate-400 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-slate-800 file:text-slate-200 hover:file:bg-slate-700 cursor-pointer">
                    <p class="text-xs text-slate-500 mt-1">Máx. 5MB. Admite separador coma (,) o punto y coma (;).</p>
                </div>

                <!-- Vista previa: al elegir el fichero se manda a /games/import/preview (sin
                     importar nada todavía) para enseñar qué columnas se han reconocido y
                     cómo quedarían las primeras filas, antes de confirmar la subida real. -->
                <div id="import-preview" class="hidden pt-4 border-t border-slate-800">
                    <p id="import-preview-error" class="hidden text-sm text-red-400 bg-red-500/10 border border-red-500/20 rounded-lg px-4 py-2"></p>

                    <div id="import-preview-content" class="hidden">
                        <div id="import-preview-columns" class="text-xs text-slate-500 mb-3"></div>

                        <div class="overflow-x-auto rounded-lg border border-slate-800">
                            <table class="min-w-full divide-y divide-slate-800 text-sm">
                                <thead class="bg-slate-800/50">
                                    <tr>
                                        <th class="px-3 py-2 text-left font-semibold text-slate-400">Título</th>
                                        <th class="px-3 py-2 text-left font-semibold text-slate-400">Plataforma</th>
                                        <th class="px-3 py-2 text-left font-semibold text-slate-400">EAN</th>
                                        <th class="px-3 py-2 text-right font-semibold text-slate-400">Precio</th>
                                    </tr>
                                </thead>
                                <tbody id="import-preview-rows" class="divide-y divide-slate-800 text-slate-300"></tbody>
                            </table>
                        </div>
                        <p class="text-xs text-slate-500 mt-2">Primeras filas de ejemplo. La importación real no tiene límite de filas.</p>

                        {{-- Duplicados (#143): findDuplicates() escanea el CSV completo, no solo
                             las filas de ejemplo de arriba. Se pintan aparte con una decisión
                             por fila (Sobrescribir/Omitir, Omitir por defecto) que viaja al
                             enviar el formulario en #import-duplicate-decisions. --}}
                        <div id="import-duplicates" class="hidden mt-4 pt-4 border-t border-slate-800">
                            <div class="flex items-center justify-between gap-3 mb-2">
                                <p class="text-sm font-medium text-amber-400">
                                    <span id="import-duplicates-count"></span> ya en tu colección (mismo título, plataforma y edición):
                                </p>
                                <div class="flex gap-3 shrink-0">
                                    <button type="button" id="import-duplicates-skip-all" class="text-xs font-medium text-slate-400 hover:text-slate-200">Omitir todos</button>
                                    <button type="button" id="import-duplicates-overwrite-all" class="text-xs font-medium text-indigo-400 hover:text-indigo-300">Sobrescribir todos</button>
                                </div>
                            </div>
                            <ul id="import-duplicates-list" class="space-y-2 max-h-64 overflow-y-auto"></ul>
                        </div>
                    </div>
                </div>

                <div class="pt-4 border-t border-slate-800 space-y-4">
                    <div>
                        <span class="{{ $label }}">Modo de importación</span>
                        <div class="flex flex-wrap gap-3 mt-1">
                            <label class="flex items-center gap-2 cursor-pointer rounded-lg border border-slate-700 px-3 py-2 text-sm text-slate-300 has-checked:border-indigo-500">
                                <input type="radio" name="mode" value="add" class="accent-indigo-500" {{ old('mode', 'add') === 'add' ? 'checked' : '' }}>
                                Añadir
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer rounded-lg border border-red-900/40 px-3 py-2 text-sm text-slate-300 has-checked:border-red-500">
                                <input type="radio" name="mode" value="replace" class="accent-red-500" {{ old('mode') === 'replace' ? 'checked' : '' }}>
                                Reemplazar
                            </label>
                        </div>
                        <p class="text-xs text-slate-500 mt-1">
                            Añadir revisa si una fila ya existe (mismo título, plataforma y edición) antes de darla de alta.
                            Reemplazar envía a la papelera los juegos del alcance elegido y los sustituye por el CSV entero.
                        </p>
                    </div>

                    <div id="import-scope-wrap" class="hidden">
                        <label for="import-scope-select" class="{{ $label }}">Alcance de Reemplazar</label>
                        <select id="import-scope-select" name="scope_platform_id" class="{{ $input }}">
                            <option value="" data-name="{{ \App\Http\Controllers\Web\PanelController::CLEAR_ALL_CONFIRM_TEXT }}" {{ (string) old('scope_platform_id', $selectedPlatformId) === '' ? 'selected' : '' }}>
                                Toda la colección
                            </option>
                            @foreach($platforms as $platform)
                                <option value="{{ $platform->id }}" data-name="{{ $platform->name }}" {{ (string) old('scope_platform_id', $selectedPlatformId) === (string) $platform->id ? 'selected' : '' }}>
                                    {{ $platform->name }} ({{ $platform->games_count }} {{ Str::plural('juego', $platform->games_count) }})
                                </option>
                            @endforeach
                            <option value="{{ \App\Http\Controllers\Web\PanelController::NO_PLATFORM_VALUE }}" data-name="Sin plataforma" {{ old('scope_platform_id', $selectedPlatformId) === \App\Http\Controllers\Web\PanelController::NO_PLATFORM_VALUE ? 'selected' : '' }}>
                                Sin plataforma ({{ $noPlatformCount }} {{ Str::plural('juego', $noPlatformCount) }})
                            </option>
                        </select>
                        @error('scope_platform_id')
                            <span class="text-red-400 text-sm mt-1 block">{{ $message }}</span>
                        @enderror
                    </div>

                    <div id="import-confirm-wrap" class="hidden">
                        <label for="import-confirm" class="{{ $label }}">
                            Escribe <span id="import-confirm-name" class="font-semibold text-red-400"></span> para confirmar
                        </label>
                        <input type="text" id="import-confirm" name="confirm" value="{{ old('confirm') }}" autocomplete="off" autocorrect="off" spellcheck="false" class="{{ $input }}">
                        @error('confirm')
                            <span class="text-red-400 text-sm mt-1 block">{{ $message }}</span>
                        @enderror
                    </div>
                </div>

                <input type="hidden" id="import-duplicate-decisions" name="duplicate_decisions" value="">

                <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-800">
                    <button type="submit" id="import-submit" class="bg-(--color-navbar) text-white px-5 py-2.5 rounded-lg text-sm font-medium hover:bg-(--color-navbar-hover) transition-colors">
                        Importar
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection
