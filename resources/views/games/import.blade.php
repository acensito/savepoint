@extends('layouts.app')

@php
    $importId = session('importId');
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
                data-status-url="{{ route('web.games.import.status', $importId) }}">
                <div id="import-status-pending" class="flex items-center gap-2 text-slate-300">
                    <x-gicon name="progress_activity" class="text-[20px] animate-spin" />
                    Importando tu colección… puede tardar un poco con ficheros grandes.
                </div>
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
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-800">
                    <button type="submit" class="bg-indigo-600 text-white px-5 py-2.5 rounded-lg text-sm font-medium hover:bg-indigo-500 transition-colors">
                        Importar
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection
