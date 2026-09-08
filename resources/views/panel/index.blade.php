@php use Illuminate\Support\Str; @endphp
@extends('layouts.app')

@php
    // Agrupadas por tipo de tarea (no solo una lista suelta): datos de la
    // colección primero (lo más habitual de usar aquí), luego la propia
    // cuenta, y por último administración de la plataforma (si aplica).
    $groups = [
        'Colección' => [
            [
                'route' => route('web.games.import'),
                'icon' => 'upload_file',
                'title' => 'Importar colección',
                'description' => 'Alta masiva de juegos desde un fichero CSV.',
            ],
            [
                'route' => route('web.games.export'),
                'icon' => 'download',
                'title' => 'Exportar colección',
                'description' => 'Descarga un CSV con toda la colección, con las mismas columnas que la importación: se puede editar y volver a importar.',
            ],
            [
                'route' => route('web.games.print'),
                'target' => '_blank',
                'icon' => 'print',
                'title' => 'Imprimir colección',
                'description' => 'Listado completo en una vista imprimible, lista para guardar como PDF desde el navegador.',
            ],
            [
                'route' => route('web.games.trash'),
                'icon' => 'delete',
                'title' => 'Papelera de reciclaje',
                'description' => $trashedCount > 0
                    ? $trashedCount . ' ' . Str::plural('juego', $trashedCount) . ' en la papelera.'
                    : 'Vacía por ahora.',
            ],
        ],
        'Cuenta' => [
            [
                'route' => route('web.profile.edit'),
                'icon' => 'account_circle',
                'title' => 'Perfil',
                'description' => 'Nombre, email y contraseña de tu cuenta.',
            ],
            [
                'route' => route('web.panel.settings'),
                'icon' => 'tune',
                'title' => 'Ajustes',
                'description' => 'Comportamiento de la app: IGDB, cuenta y colección.',
            ],
        ],
    ];

    if (auth()->user()->is_admin) {
        $groups['Administración'] = [
            [
                'route' => route('web.panel.users.index'),
                'icon' => 'group',
                'title' => 'Usuarios',
                'description' => 'Listar, dar de alta, editar y borrar las cuentas de la plataforma.',
            ],
        ];
    }
@endphp

@section('content')
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-slate-100 tracking-tight">Panel de control</h1>
        <p class="text-slate-400 mt-1">Importar, exportar y otras tareas que no son del día a día con tu colección.</p>
    </div>

    <div class="space-y-8">
        @foreach($groups as $groupName => $cards)
            <div>
                <h2 class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-3">{{ $groupName }}</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    @foreach($cards as $card)
                        <a href="{{ $card['route'] }}" @if(isset($card['target'])) target="{{ $card['target'] }}"
                           rel="noopener" @endif
                           class="group flex items-start gap-4 bg-slate-900 border border-slate-800 rounded-xl p-6 hover:border-indigo-500/50 transition-colors">
                            <div
                                class="w-10 h-10 rounded-lg bg-indigo-500/10 flex items-center justify-center shrink-0">
                                <x-gicon name="{{ $card['icon'] }}" class="text-[20px] text-indigo-400"/>
                            </div>
                            <div class="min-w-0">
                                <h3 class="text-sm font-semibold text-slate-100 group-hover:text-indigo-300 transition-colors">{{ $card['title'] }}</h3>
                                <p class="text-xs text-slate-500 mt-1">{{ $card['description'] }}</p>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        @endforeach

        {{-- #144: zona de peligro, separada del resto de tarjetas (que solo
             navegan) porque esta sí actúa de inmediato. Escribir el nombre
             exacto de la plataforma habilita el botón — mismo patrón que
             confirmar el borrado de un repo en GitHub, comprobado también en
             servidor (ver PanelController::clearPlatformGames). --}}
        <div>
            <h2 class="text-xs font-semibold text-red-500 uppercase tracking-wider mb-3">Zona de peligro</h2>
            <div class="bg-red-950/20 border border-red-900/40 rounded-xl p-6">
                <h3 class="text-sm font-semibold text-slate-100 mb-1">Vaciar una plataforma</h3>
                <p class="text-xs text-slate-500 mb-5">
                    Envía a la papelera todos tus juegos de la plataforma elegida. La plataforma en sí no se borra, se
                    queda vacía. Es recuperable desde la papelera mientras no la vacíes también.
                </p>

                <form id="clear-platform-form" method="POST"
                      data-url-template="{{ route('web.panel.platforms.clear-games', ['platform' => '__ID__']) }}"
                      class="space-y-4 max-w-sm">
                    @csrf
                    @method('DELETE')

                    <div>
                        <label for="clear-platform-select" class="block font-medium text-sm text-slate-300 mb-1">Plataforma</label>
                        <select id="clear-platform-select" class="w-full rounded-lg border border-slate-700 bg-slate-800 text-slate-100 px-4 py-2 focus:border-red-500 focus:ring-red-500 outline-hidden">
                            <option value="">Elige una plataforma…</option>
                            @foreach($platforms as $platform)
                                <option value="{{ $platform->id }}" data-name="{{ $platform->name }}">
                                    {{ $platform->name }} ({{ $platform->games_count }} {{ Str::plural('juego', $platform->games_count) }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div id="clear-platform-confirm-wrap" class="hidden">
                        <label for="clear-platform-confirm" class="block font-medium text-sm text-slate-300 mb-1">
                            Escribe <span id="clear-platform-confirm-name" class="font-semibold text-red-400"></span> para confirmar
                        </label>
                        <input type="text" id="clear-platform-confirm" name="confirm" autocomplete="off" autocorrect="off" spellcheck="false"
                               class="w-full rounded-lg border border-slate-700 bg-slate-800 text-slate-100 px-4 py-2 focus:border-red-500 focus:ring-red-500 outline-hidden">
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
        </div>
    </div>
@endsection
