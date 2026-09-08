@extends('layouts.app')

@section('content')
    <div class="max-w-2xl mx-auto py-6">
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-slate-100 tracking-tight">Acerca de Savepoint</h1>
            <p class="text-slate-400 mt-1">Versión desplegada, últimas novedades y estado de los servicios de esta instancia.</p>
        </div>

        <div class="space-y-6">
            <div class="bg-slate-900 border border-slate-800 rounded-xl p-8">
                <p class="text-sm text-slate-300">
                    <span class="font-semibold text-slate-100">Tu colección de videojuegos, catalogada de verdad — y solo tuya.</span>
                    Savepoint es 100% autoalojada: se despliega con Docker en tu propio servidor, guarda los datos en tu
                    Postgres y no depende de ninguna clave de API que no sea la tuya.
                </p>

                <div class="mt-6 flex flex-wrap items-center gap-x-6 gap-y-2 text-sm">
                    <div>
                        <span class="text-slate-500">Versión desplegada</span>
                        <p class="text-slate-100 font-medium">{{ $changelog['date'] ?? 'Desconocida' }}</p>
                    </div>
                    <a href="https://github.com/acensito/savepoint" target="_blank" rel="noopener"
                       class="text-indigo-400 hover:underline">Código fuente en GitHub</a>
                    @if($discordUrl)
                        <a href="{{ $discordUrl }}" target="_blank" rel="noopener"
                           class="flex items-center gap-1.5 text-indigo-400 hover:underline">
                            <x-gicon name="forum" class="text-[18px]"/>
                            Comunidad en Discord
                        </a>
                    @endif
                </div>
            </div>

            @if($changelog && count($changelog['items']) > 0)
                <div class="bg-slate-900 border border-slate-800 rounded-xl p-8">
                    <h2 class="text-lg font-semibold text-slate-100 mb-1">Últimas novedades</h2>
                    <p class="text-sm text-slate-500 mb-4">Del {{ $changelog['date'] }}, según <a href="https://github.com/acensito/savepoint/blob/main/CHANGELOG.md" target="_blank" rel="noopener" class="text-indigo-400 hover:underline">CHANGELOG.md</a>.</p>

                    <ul class="space-y-2">
                        @foreach($changelog['items'] as $item)
                            <li class="flex items-start gap-2 text-sm text-slate-300">
                                <x-gicon name="check_circle" class="text-[18px] text-emerald-500 mt-0.5 shrink-0"/>
                                {{ $item }}
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="bg-slate-900 border border-slate-800 rounded-xl p-8">
                <h2 class="text-lg font-semibold text-slate-100 mb-4">Estado de los servicios</h2>

                @php
                    $rows = [
                        'Aplicación' => true,
                        'Base de datos' => $health['database'],
                        'Redis (caché y colas)' => $health['redis'],
                    ];
                @endphp

                <ul class="space-y-3">
                    @foreach($rows as $label => $ok)
                        <li class="flex items-center gap-2 text-sm">
                            <x-gicon name="{{ $ok ? 'check_circle' : 'error' }}" class="text-[18px] {{ $ok ? 'text-emerald-500' : 'text-red-500' }}"/>
                            <span class="text-slate-300">{{ $label }}</span>
                            <span class="ml-auto {{ $ok ? 'text-emerald-500' : 'text-red-500' }}">{{ $ok ? 'Operativo' : 'Caído' }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
@endsection
