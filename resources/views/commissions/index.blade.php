@extends('layouts.app')

@section('content')
    <div class="mb-8 flex flex-col sm:flex-row sm:justify-between sm:items-end gap-4">
        <div>
            <h1 class="text-3xl font-bold text-slate-100 tracking-tight">Encargos</h1>
            <p class="text-slate-400 mt-1">Juegos que te compran/envían amigos, o que tú compras/envías a alguien.</p>
        </div>

        <a href="{{ route('web.commissions.create') }}" class="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-indigo-500 transition-colors whitespace-nowrap">
            + Añadir encargo
        </a>
    </div>

    <div class="flex items-center gap-2 mb-4 text-sm">
        @php
            $tabClass = fn (bool $active) => 'px-3 py-1.5 rounded-lg font-medium transition-colors '
                . ($active ? 'bg-indigo-500/10 text-indigo-300' : 'text-slate-400 hover:bg-slate-800 hover:text-slate-100');
        @endphp
        <a href="{{ route('web.commissions.index') }}" class="{{ $tabClass($direction === '') }}">Todos</a>
        <a href="{{ route('web.commissions.index', ['direction' => 'owed_to_me']) }}" class="{{ $tabClass($direction === 'owed_to_me') }}">Me deben</a>
        <a href="{{ route('web.commissions.index', ['direction' => 'owed_by_me']) }}" class="{{ $tabClass($direction === 'owed_by_me') }}">Debo</a>
    </div>

    @if($commissions->isEmpty())
        <div class="bg-slate-900 border border-slate-800 rounded-xl px-6 py-12 text-center text-slate-500 text-sm">
            No tienes ningún encargo{{ $direction !== '' ? ' en este filtro' : '' }} todavía.
        </div>
    @else
        <div class="bg-slate-900 border border-slate-800 rounded-xl overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-800">
                <thead class="bg-slate-800/50">
                    <tr>
                        <th scope="col" class="px-6 py-3.5 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Título</th>
                        <th scope="col" class="px-6 py-3.5 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Plataforma</th>
                        <th scope="col" class="px-6 py-3.5 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">A quién</th>
                        <th scope="col" class="px-6 py-3.5 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Dirección</th>
                        <th scope="col" class="px-6 py-3.5 text-right text-xs font-semibold text-slate-400 uppercase tracking-wider">Precio</th>
                        <th scope="col" class="px-6 py-3.5 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Fecha compra</th>
                        <th scope="col" class="px-6 py-3.5 text-right text-xs font-semibold text-slate-400 uppercase tracking-wider">Estado / Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    @foreach($commissions as $commission)
                        <tr class="hover:bg-slate-800/40 transition-colors align-top">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-slate-100">{{ $commission->title }}</td>
                            <td class="px-6 py-4 whitespace-nowrap"><x-platform-chip :platform="$commission->platform" /></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-300">{{ $commission->counterparty_name }}</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($commission->direction === 'owed_by_me')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-semibold bg-amber-500/10 text-amber-300 border border-amber-500/30">Debo</span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-semibold bg-emerald-500/10 text-emerald-300 border border-emerald-500/30">Me deben</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-slate-300 tabular-nums">
                                {{ $commission->price !== null ? number_format($commission->price, 2, ',', '.') . ' €' : '—' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-300">{{ $commission->purchased_at?->format('d/m/Y') ?? '—' }}</td>
                            <td class="px-6 py-4 text-right text-sm font-medium">
                                @if($commission->isResolved())
                                    <div class="text-slate-300">
                                        {{ $commission->resolvedLabel() }}
                                        @if($commission->game_id)
                                            <a href="{{ route('web.games.show', $commission->game_id) }}" class="block text-indigo-400 hover:underline text-xs mt-0.5">
                                                Ver en tu colección
                                            </a>
                                        @endif
                                    </div>
                                @else
                                    <div class="flex items-center justify-end gap-4">
                                        <a href="{{ route('web.commissions.edit', $commission->id) }}" class="text-slate-400 hover:text-slate-100 transition-colors">Editar</a>
                                        <button type="button" class="js-resolve-trigger text-emerald-400 hover:text-emerald-300 transition-colors"
                                            data-target="resolve-panel-{{ $commission->id }}">
                                            {{ $commission->direction === 'owed_by_me' ? 'Marcar como enviado' : 'Marcar como recibido' }}
                                        </button>
                                        <form action="{{ route('web.commissions.destroy', $commission->id) }}" method="POST" class="js-confirm-delete"
                                            data-confirm-title="Borrar encargo"
                                            data-confirm-message="«{{ $commission->title }}» se borrará definitivamente.">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-slate-400 hover:text-red-400 transition-colors">Borrar</button>
                                        </form>
                                    </div>

                                    <div id="resolve-panel-{{ $commission->id }}" class="hidden mt-3 bg-slate-800/40 border border-slate-800 rounded-lg p-3 text-left">
                                        <form action="{{ route('web.commissions.resolve', $commission->id) }}" method="POST" class="flex items-end gap-2">
                                            @csrf
                                            <div class="flex-1">
                                                <label class="block text-xs text-slate-400 mb-1">
                                                    {{ $commission->direction === 'owed_by_me' ? 'Fecha de envío' : 'Fecha de recepción' }}
                                                </label>
                                                <input type="date" name="resolved_at" value="{{ now()->toDateString() }}"
                                                    class="w-full rounded-lg border border-slate-700 bg-slate-800 text-slate-100 px-3 py-1.5 text-sm focus:border-indigo-500 focus:ring-indigo-500 outline-none">
                                            </div>
                                            <button type="submit" class="bg-emerald-600 hover:bg-emerald-500 text-white px-3 py-1.5 rounded-lg text-sm font-medium whitespace-nowrap">
                                                Confirmar
                                            </button>
                                        </form>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <script>
        // Delegado en la tabla: cada fila pendiente tiene su propio panel de
        // fecha (data-target), evita un listener repetido por fila.
        document.querySelectorAll('.js-resolve-trigger').forEach((trigger) => {
            trigger.addEventListener('click', () => {
                document.getElementById(trigger.dataset.target)?.classList.remove('hidden');
            });
        });
    </script>
@endsection
