@extends('layouts.app')

@php
    $batchId = session('batchId');
@endphp

@section('content')
    <div class="max-w-2xl mx-auto py-6">
        <div class="mb-8 flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-slate-100 tracking-tight">Identificar carátulas</h1>
                <p class="text-slate-400 mt-1">Busca carátula/EAN en bloque para los juegos sin carátula de una plataforma.</p>
            </div>
            <a href="{{ route('web.panel.index') }}" class="text-sm font-medium text-slate-400 hover:text-slate-100">
                ← Volver al panel
            </a>
        </div>

        @if($batchId)
            {{-- El lote se procesa en segundo plano (ver
                 Jobs\IdentifyMissingGameCovers, GameAutoIdentifyController::store()):
                 este bloque sondea /games/auto-identify/status/{id} (ver
                 initAutoIdentifyStatusPolling en app.js) hasta que termina, y
                 entonces pinta la cola de revisión con los candidatos encontrados. --}}
            <div id="auto-identify-status" class="bg-slate-900 border border-slate-800 rounded-xl p-6 mb-6"
                data-status-url="{{ route('web.games.auto-identify.status', $batchId) }}"
                data-confirm-url="{{ route('web.games.auto-identify.confirm', $batchId) }}"
                data-csrf-token="{{ csrf_token() }}"
                data-edit-url-template="{{ route('web.games.edit', ':id') }}">
                <div id="auto-identify-status-pending" class="flex items-center gap-2 text-slate-300">
                    <x-gicon name="progress_activity" class="text-[20px] animate-spin" />
                    <span id="auto-identify-status-pending-text">Buscando candidatos en CEX… puede tardar un poco según cuántos juegos falten.</span>
                </div>
                <div id="auto-identify-status-result" class="hidden"></div>
            </div>
        @endif

        <div class="bg-slate-900 border border-slate-800 rounded-xl p-8">
            <p class="text-sm text-slate-400 mb-4">
                Solo se buscan los juegos sin carátula de la plataforma elegida. Con EAN ya conocido se busca por
                él (identifica la copia exacta); sin EAN, se busca por título — más ambiguo, así que solo se
                propone un candidato cuando no hay dudas razonables entre los resultados. Nada se guarda sin que lo
                confirmes en la cola de revisión.
            </p>

            @if($platforms->isEmpty())
                <p class="text-sm text-slate-500">No hay ninguna plataforma con juegos sin carátula pendientes ahora mismo.</p>
            @else
                <form action="{{ route('web.games.auto-identify.store') }}" method="POST" class="flex items-end gap-3">
                    @csrf

                    <div class="flex-1">
                        <label for="platform_id" class="block font-medium text-sm text-slate-300 mb-1">Plataforma</label>
                        <select name="platform_id" id="platform_id" required
                            class="w-full rounded-lg border border-slate-700 bg-slate-800 text-slate-100 px-4 py-2 focus:border-indigo-500 focus:ring-indigo-500 outline-hidden">
                            @foreach($platforms as $entry)
                                <option value="{{ $entry['platform']->id }}">
                                    {{ $entry['platform']->name }} ({{ $entry['count'] }} sin carátula)
                                </option>
                            @endforeach
                        </select>
                        @error('platform_id') <span class="text-red-400 text-sm mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <button type="submit" class="bg-(--color-navbar) text-white px-5 py-2.5 rounded-lg text-sm font-medium hover:bg-(--color-navbar-hover) transition-colors">
                        Buscar candidatos
                    </button>
                </form>
            @endif
        </div>
    </div>
@endsection
