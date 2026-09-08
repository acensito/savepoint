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

    // #144: grupo aparte (no dentro de "Colección") para que se note a
    // simple vista que esto no es una tarjeta de navegación más — el propio
    // color rojo del icono/título ya avisa antes de entrar siquiera. La
    // acción de verdad (elegir plataforma o vaciar todo, con confirmación)
    // vive en su propia página (panel.danger-zone), no aquí.
    $groups['Zona de peligro'] = [
        [
            'route' => route('web.panel.danger-zone'),
            'icon' => 'warning',
            'color' => 'red',
            'title' => 'Vaciar la colección',
            'description' => 'Enviar a la papelera de golpe una plataforma concreta, o la colección entera.',
        ],
    ];
@endphp

@section('content')
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-slate-100 tracking-tight">Panel de control</h1>
        <p class="text-slate-400 mt-1">Importar, exportar y otras tareas que no son del día a día con tu colección.</p>
    </div>

    <div class="space-y-8">
        @foreach($groups as $groupName => $cards)
            @php $groupColor = $groupName === 'Zona de peligro' ? 'red' : 'indigo'; @endphp
            <div>
                <h2 class="text-xs font-semibold uppercase tracking-wider mb-3 {{ $groupColor === 'red' ? 'text-red-500' : 'text-slate-500' }}">{{ $groupName }}</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    @foreach($cards as $card)
                        @php $cardColor = $card['color'] ?? 'indigo'; @endphp
                        <a href="{{ $card['route'] }}" @if(isset($card['target'])) target="{{ $card['target'] }}"
                           rel="noopener" @endif
                           class="group flex items-start gap-4 bg-slate-900 border rounded-xl p-6 transition-colors {{ $cardColor === 'red' ? 'border-red-900/40 hover:border-red-500/50' : 'border-slate-800 hover:border-indigo-500/50' }}">
                            <div
                                class="w-10 h-10 rounded-lg flex items-center justify-center shrink-0 {{ $cardColor === 'red' ? 'bg-red-500/10' : 'bg-indigo-500/10' }}">
                                <x-gicon name="{{ $card['icon'] }}" class="text-[20px] {{ $cardColor === 'red' ? 'text-red-400' : 'text-indigo-400' }}"/>
                            </div>
                            <div class="min-w-0">
                                <h3 class="text-sm font-semibold transition-colors {{ $cardColor === 'red' ? 'text-slate-100 group-hover:text-red-300' : 'text-slate-100 group-hover:text-indigo-300' }}">{{ $card['title'] }}</h3>
                                <p class="text-xs text-slate-500 mt-1">{{ $card['description'] }}</p>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
@endsection
