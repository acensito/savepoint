@extends('layouts.app')

@php
    $result = session('importResult');
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

        @if($result)
            <div class="bg-slate-900 border border-slate-800 rounded-xl p-6 mb-6">
                <div class="flex items-center gap-2 text-emerald-400 font-semibold">
                    <x-gicon name="check_circle" class="text-[20px]" />
                    {{ $result['imported'] }} {{ \Illuminate\Support\Str::plural('juego', $result['imported']) }} {{ \Illuminate\Support\Str::plural('importado', $result['imported']) }}.
                </div>

                @if($result['createdPlatforms'] || $result['createdEditions'])
                    <p class="text-sm text-slate-400 mt-2">
                        Creadas sobre la marcha: {{ $result['createdPlatforms'] }} {{ \Illuminate\Support\Str::plural('plataforma', $result['createdPlatforms']) }}
                        y {{ $result['createdEditions'] }} {{ \Illuminate\Support\Str::plural('edición', $result['createdEditions']) }}.
                    </p>
                @endif

                @if(count($result['errors']))
                    <div class="mt-4 pt-4 border-t border-slate-800">
                        <p class="text-sm font-medium text-amber-400 mb-2">
                            {{ count($result['errors']) }} {{ \Illuminate\Support\Str::plural('fila', count($result['errors'])) }} con incidencias:
                        </p>
                        <ul class="text-sm text-slate-400 space-y-1 list-disc list-inside max-h-48 overflow-y-auto">
                            @foreach($result['errors'] as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
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
                        class="block w-full text-sm text-slate-400 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-slate-800 file:text-slate-200 hover:file:bg-slate-700 cursor-pointer">
                    <p class="text-xs text-slate-500 mt-1">Máx. 5MB. Admite separador coma (,) o punto y coma (;).</p>
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
