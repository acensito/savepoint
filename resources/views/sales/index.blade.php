@extends('layouts.app')

@section('content')
    <div class="mb-8 flex flex-col sm:flex-row sm:justify-between sm:items-end gap-4">
        <div>
            <h1 class="text-3xl font-bold text-slate-100 tracking-tight">Ventas</h1>
            <p class="text-slate-400 mt-1">Histórico de juegos vendidos, agrupado por año.</p>
        </div>

        <a href="{{ route('web.games.index') }}" class="text-sm font-medium text-slate-400 hover:text-slate-100">
            ← Volver a mi colección
        </a>
    </div>

    @if($byYear->isEmpty())
        <div class="bg-slate-900 border border-slate-800 rounded-xl px-6 py-12 text-center text-slate-500 text-sm">
            Todavía no has marcado ningún juego como vendido.
        </div>
    @else
        <div class="space-y-8">
            @foreach($byYear as $year => $data)
                <div>
                    <div class="flex flex-wrap items-baseline justify-between gap-3 mb-3">
                        <h2 class="text-xl font-bold text-slate-100">{{ $year }}</h2>
                        <div class="flex flex-wrap items-center gap-x-5 gap-y-1 text-sm text-slate-400">
                            <span>{{ $data['count'] }} {{ Str::plural('venta', $data['count']) }}</span>
                            <span>Invertido: <span class="text-slate-200 font-medium tabular-nums">{{ number_format($data['paid'], 2, ',', '.') }} €</span></span>
                            <span>Obtenido: <span class="text-slate-200 font-medium tabular-nums">{{ number_format($data['sold'], 2, ',', '.') }} €</span></span>
                            <span>
                                Beneficio:
                                <span class="font-semibold tabular-nums {{ $data['profit'] >= 0 ? 'text-emerald-400' : 'text-red-400' }}">
                                    {{ $data['profit'] >= 0 ? '+' : '' }}{{ number_format($data['profit'], 2, ',', '.') }} €
                                    @if($data['profit_percent'] !== null)
                                        ({{ $data['profit_percent'] >= 0 ? '+' : '' }}{{ number_format($data['profit_percent'], 1, ',', '.') }} %)
                                    @endif
                                </span>
                            </span>
                        </div>
                    </div>

                    <div class="bg-slate-900 border border-slate-800 rounded-xl overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-800">
                            <thead class="bg-slate-800/50">
                                <tr>
                                    <th scope="col" class="px-6 py-3.5 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Título</th>
                                    <th scope="col" class="px-6 py-3.5 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Plataforma</th>
                                    <th scope="col" class="px-6 py-3.5 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Edición</th>
                                    <th scope="col" class="px-6 py-3.5 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Región</th>
                                    <th scope="col" class="px-6 py-3.5 text-center text-xs font-semibold text-slate-400 uppercase tracking-wider">Conservación</th>
                                    <th scope="col" class="px-6 py-3.5 text-right text-xs font-semibold text-slate-400 uppercase tracking-wider">Compra</th>
                                    <th scope="col" class="px-6 py-3.5 text-right text-xs font-semibold text-slate-400 uppercase tracking-wider">Venta</th>
                                    <th scope="col" class="px-6 py-3.5 text-right text-xs font-semibold text-slate-400 uppercase tracking-wider">Rendimiento</th>
                                    <th scope="col" class="px-6 py-3.5 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Fecha venta</th>
                                    <th scope="col" class="px-6 py-3.5 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Notas</th>
                                    <th scope="col" class="px-6 py-3.5 text-right text-xs font-semibold text-slate-400 uppercase tracking-wider">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-800">
                                @foreach($data['games'] as $game)
                                    @php
                                        $rowPaid = (float) ($game->price_paid ?? 0);
                                        $rowSold = (float) ($game->sale_price ?? 0);
                                        $rowProfit = $rowSold - $rowPaid;
                                        $rowPercent = $rowPaid > 0 ? round($rowProfit / $rowPaid * 100, 1) : null;
                                    @endphp
                                    <tr class="hover:bg-slate-800/40 transition-colors">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-slate-100">{{ $game->title }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap"><x-platform-chip :platform="$game->platform" /></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-300">{{ $game->edition?->name ?? '—' }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-300">{{ $game->region ?? '—' }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-center">
                                            <x-star-rating :rating="$game->rating" size="text-[10px]" class="justify-center" />
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-slate-300 tabular-nums">
                                            {{ $game->price_paid !== null ? number_format($game->price_paid, 2, ',', '.') . ' €' : '—' }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-slate-300 tabular-nums">
                                            {{ number_format($game->sale_price, 2, ',', '.') }} €
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium tabular-nums {{ $rowProfit >= 0 ? 'text-emerald-400' : 'text-red-400' }}">
                                            {{ $rowProfit >= 0 ? '+' : '' }}{{ number_format($rowProfit, 2, ',', '.') }} €
                                            @if($rowPercent !== null)
                                                <span class="text-slate-500 font-normal">({{ $rowPercent >= 0 ? '+' : '' }}{{ number_format($rowPercent, 1, ',', '.') }}%)</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-300">{{ $game->sold_at->format('d/m/Y') }}</td>
                                        <td class="px-6 py-4 text-sm text-slate-400 max-w-xs truncate" title="{{ $game->notes }}">{{ $game->notes ?? '—' }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <form action="{{ route('web.sales.restore', $game->id) }}" method="POST" class="inline js-confirm-delete"
                                                data-confirm-title="Deshacer venta"
                                                data-confirm-message="«{{ $game->title }}» volverá a tu colección, sin datos de venta."
                                                data-confirm-accept="Deshacer venta">
                                                @csrf
                                                <button type="submit" class="text-emerald-400 hover:text-emerald-300 transition-colors">
                                                    Deshacer venta
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
@endsection
