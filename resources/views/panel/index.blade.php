@extends('layouts.app')

@php
    $cards = [
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
                ? $trashedCount . ' ' . \Illuminate\Support\Str::plural('juego', $trashedCount) . ' en la papelera.'
                : 'Vacía por ahora.',
        ],
        [
            'route' => route('web.profile.edit'),
            'icon' => 'account_circle',
            'title' => 'Perfil',
            'description' => 'Nombre, email y contraseña de tu cuenta.',
        ],
    ];
@endphp

@section('content')
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-slate-100 tracking-tight">Panel de control</h1>
        <p class="text-slate-400 mt-1">Importar, exportar y otras tareas que no son del día a día con tu colección.</p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        @foreach($cards as $card)
            <a href="{{ $card['route'] }}" @if(isset($card['target'])) target="{{ $card['target'] }}" rel="noopener" @endif
                class="group flex items-start gap-4 bg-slate-900 border border-slate-800 rounded-xl p-6 hover:border-indigo-500/50 transition-colors">
                <div class="w-10 h-10 rounded-lg bg-indigo-500/10 flex items-center justify-center flex-shrink-0">
                    <x-gicon name="{{ $card['icon'] }}" class="text-[20px] text-indigo-400" />
                </div>
                <div class="min-w-0">
                    <h2 class="text-sm font-semibold text-slate-100 group-hover:text-indigo-300 transition-colors">{{ $card['title'] }}</h2>
                    <p class="text-xs text-slate-500 mt-1">{{ $card['description'] }}</p>
                </div>
            </a>
        @endforeach
    </div>
@endsection
