@php use Illuminate\Support\Str; @endphp
@extends('layouts.app')

@section('content')
    @php
        $input = 'w-full rounded-lg border border-slate-700 bg-slate-800 text-slate-100 px-4 py-2 focus:border-red-500 focus:ring-red-500 outline-hidden';
        $label = 'block font-medium text-sm text-slate-300 mb-1';
    @endphp

    <div class="max-w-2xl mx-auto py-6">
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-red-400 tracking-tight">Zona de peligro</h1>
            <p class="text-slate-400 mt-1">
                Acciones que envían juegos a la papelera de golpe. Son recuperables desde la
                <a href="{{ route('web.games.trash') }}" class="text-indigo-400 hover:underline">papelera</a>
                mientras no la vacíes también.
            </p>
        </div>

        <div class="space-y-6">
            {{-- #144: elegir plataforma + teclear su nombre exacto habilita el botón
                 (mismo patrón que confirmar el borrado de un repo en GitHub),
                 comprobado también en servidor (ver
                 PanelController::clearPlatformGames) para que saltarse el JS no baste. --}}
            <div class="bg-red-950/20 border border-red-900/40 rounded-xl p-8">
                <h2 class="text-lg font-semibold text-slate-100 mb-1">Vaciar una plataforma</h2>
                <p class="text-sm text-slate-500 mb-6">
                    Envía a la papelera todos tus juegos de la plataforma elegida. La plataforma en sí no se
                    borra, se queda vacía para reutilizarla o borrarla aparte.
                </p>

                <form id="clear-platform-form" method="POST"
                      data-url-template="{{ route('web.panel.platforms.clear-games', ['platform' => '__ID__']) }}"
                      class="space-y-4">
                    @csrf
                    @method('DELETE')

                    <div>
                        <label for="clear-platform-select" class="{{ $label }}">Plataforma</label>
                        <select id="clear-platform-select" class="{{ $input }}">
                            <option value="">Elige una plataforma…</option>
                            @foreach($platforms as $platform)
                                <option value="{{ $platform->id }}" data-name="{{ $platform->name }}">
                                    {{ $platform->name }} ({{ $platform->games_count }} {{ Str::plural('juego', $platform->games_count) }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div id="clear-platform-confirm-wrap" class="hidden">
                        <label for="clear-platform-confirm" class="{{ $label }}">
                            Escribe <span id="clear-platform-confirm-name" class="font-semibold text-red-400"></span> para confirmar
                        </label>
                        <input type="text" id="clear-platform-confirm" name="confirm" autocomplete="off" autocorrect="off" spellcheck="false" class="{{ $input }}">
                        @error('confirm')
                            <span class="text-red-400 text-sm mt-1 block">{{ $message }}</span>
                        @enderror
                    </div>

                    <button type="submit" id="clear-platform-submit" disabled
                            class="bg-red-600 hover:bg-red-500 disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:bg-red-600 text-white px-5 py-2.5 rounded-lg text-sm font-medium transition-colors">
                        Vaciar plataforma
                    </button>
                </form>
            </div>

            {{-- Sin selector (no hay un nombre propio que teclear para "todo"): en su
                 lugar, un texto fijo (PanelController::CLEAR_ALL_CONFIRM_TEXT). --}}
            <div class="bg-red-950/20 border border-red-900/40 rounded-xl p-8">
                <h2 class="text-lg font-semibold text-slate-100 mb-1">Vaciar toda la colección</h2>
                <p class="text-sm text-slate-500 mb-6">
                    Envía a la papelera tus {{ $totalGames }} {{ Str::plural('juego', $totalGames) }}, de cualquier
                    plataforma. Úsalo solo si de verdad quieres empezar de cero.
                </p>

                <form id="clear-all-form" method="POST" action="{{ route('web.panel.games.clear') }}"
                      data-confirm-text="{{ \App\Http\Controllers\Web\PanelController::CLEAR_ALL_CONFIRM_TEXT }}" class="space-y-4">
                    @csrf
                    @method('DELETE')

                    <div>
                        <label for="clear-all-confirm" class="{{ $label }}">
                            Escribe <span class="font-semibold text-red-400">{{ \App\Http\Controllers\Web\PanelController::CLEAR_ALL_CONFIRM_TEXT }}</span> para confirmar
                        </label>
                        <input type="text" id="clear-all-confirm" name="confirm" autocomplete="off" autocorrect="off" spellcheck="false" class="{{ $input }}">
                        @error('confirm_all')
                            <span class="text-red-400 text-sm mt-1 block">{{ $message }}</span>
                        @enderror
                    </div>

                    <button type="submit" id="clear-all-submit" disabled
                            class="bg-red-600 hover:bg-red-500 disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:bg-red-600 text-white px-5 py-2.5 rounded-lg text-sm font-medium transition-colors">
                        Vaciar toda la colección
                    </button>
                </form>
            </div>
        </div>
    </div>
@endsection
