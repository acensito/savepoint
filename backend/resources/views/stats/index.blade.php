@extends('layouts.app')

@section('content')
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-slate-100 tracking-tight">Estadísticas</h1>
        <p class="text-slate-400 mt-1">Un vistazo general a tu colección.</p>
    </div>

    <!-- KPIs -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <div class="bg-slate-900 border border-slate-800 rounded-xl p-6">
            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Total de juegos</p>
            <p class="text-3xl font-semibold text-slate-100 mt-2">{{ number_format($totalGames, 0, ',', '.') }}</p>
        </div>
        <div class="bg-slate-900 border border-slate-800 rounded-xl p-6">
            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Gasto total</p>
            <p class="text-3xl font-semibold text-slate-100 mt-2">{{ number_format($totalSpent, 2, ',', '.') }} €</p>
        </div>
        <div class="bg-slate-900 border border-slate-800 rounded-xl p-6">
            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Valoración media</p>
            <p class="text-3xl font-semibold text-slate-100 mt-2">
                {{ $averageRating ? number_format($averageRating, 1, ',', '.') : '—' }}
            </p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <!-- Por plataforma -->
        <div class="bg-slate-900 border border-slate-800 rounded-xl p-6 lg:col-span-2">
            <h2 class="text-sm font-semibold text-slate-300 uppercase tracking-wider mb-5">Juegos por plataforma</h2>

            @if($byPlatform->isEmpty())
                <p class="text-sm text-slate-500">Todavía no hay juegos registrados.</p>
            @else
                <div class="space-y-3">
                    @foreach($byPlatform as $row)
                        <div class="flex items-center gap-4">
                            <div class="w-40 flex-shrink-0">
                                <x-platform-chip :platform="$row['platform']" class="max-w-full" />
                            </div>
                            <div class="flex-1 h-3 rounded-full bg-slate-800 overflow-hidden">
                                <div class="h-full bg-indigo-500 rounded-r-[4px]"
                                    style="width: {{ max($row['percent'], 3) }}%"
                                    title="{{ $row['platform']?->name ?? 'Sin plataforma' }}: {{ $row['total'] }} juego(s)"></div>
                            </div>
                            <div class="w-8 flex-shrink-0 text-right text-sm font-medium text-slate-300 tabular-nums">{{ $row['total'] }}</div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <!-- Estado de juego -->
        <div class="bg-slate-900 border border-slate-800 rounded-xl p-6">
            <h2 class="text-sm font-semibold text-slate-300 uppercase tracking-wider mb-5">Estado de juego</h2>
            <x-stacked-bar :segments="$byPlayStatus" />
        </div>

        <!-- Propiedad -->
        <div class="bg-slate-900 border border-slate-800 rounded-xl p-6">
            <h2 class="text-sm font-semibold text-slate-300 uppercase tracking-wider mb-5">Propiedad</h2>
            <x-stacked-bar :segments="$byStatus" />
        </div>
    </div>
@endsection
