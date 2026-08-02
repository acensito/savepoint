@extends('layouts.app')

@php
    $label = 'text-xs font-semibold text-slate-500 uppercase tracking-wider';
    $value = 'text-sm text-slate-200 mt-1';
@endphp

@section('content')
    <div class="max-w-3xl mx-auto py-6">
        <div class="mb-6 flex items-center justify-between gap-3">
            <a href="{{ route('web.games.index') }}" class="text-sm font-medium text-slate-400 hover:text-slate-100">
                ← Volver a mi colección
            </a>

            <div class="flex items-center gap-2">
                <a href="{{ route('web.games.edit', $game->id) }}"
                    class="flex items-center gap-1.5 bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-indigo-500 transition-colors">
                    <x-gicon name="edit" class="text-[16px]" />
                    Editar
                </a>

                <form action="{{ route('web.games.destroy', $game->id) }}" method="POST" class="js-confirm-delete"
                    data-confirm-title="Enviar a la papelera"
                    data-confirm-message="«{{ $game->title }}» se moverá a la papelera. Podrás restaurarlo más tarde.">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                        class="flex items-center justify-center w-10 h-10 rounded-lg border border-slate-700 text-slate-400 hover:bg-slate-800 hover:text-red-400 transition-colors"
                        aria-label="Borrar {{ $game->title }}">
                        <x-gicon name="delete" class="text-[18px]" />
                    </button>
                </form>
            </div>
        </div>

        <div class="bg-slate-900 border border-slate-800 rounded-xl p-6 sm:p-8">
            <div class="flex flex-col sm:flex-row gap-6">
                <x-game-cover :game="$game" size="lg" class="!w-32 !rounded-xl !text-3xl mx-auto sm:mx-0 flex-shrink-0" />

                <div class="flex-1 min-w-0">
                    <h1 class="text-2xl font-bold text-slate-100 tracking-tight">{{ $game->title }}</h1>

                    <div class="mt-2 flex items-center gap-2 flex-wrap">
                        <x-platform-chip :platform="$game->platform" />
                        @if($game->edition)
                            <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium bg-slate-800 text-slate-300 border border-slate-700">
                                {{ $game->edition->name }}
                            </span>
                        @endif
                    </div>

                    <div class="mt-3 flex items-center gap-3">
                        <x-star-rating :rating="$game->rating" size="text-[16px]" />
                        @if($game->price_paid !== null)
                            <span class="text-sm font-semibold text-emerald-400 tabular-nums">{{ number_format($game->price_paid, 2, ',', '.') }} €</span>
                        @endif
                    </div>

                    <div class="mt-3 flex items-center gap-1.5 text-sm {{ $game->play_status === 'finished' ? 'text-emerald-400' : 'text-slate-400' }}">
                        @if($game->play_status === 'finished')
                            <x-gicon name="check_circle" class="text-[18px]" />
                        @else
                            <x-gicon name="schedule" class="text-[18px]" />
                        @endif
                        {{ ['pending' => 'Pendiente', 'playing' => 'Jugando', 'finished' => 'Terminado'][$game->play_status] ?? $game->play_status }}

                        @php
                            $statusLabels = ['owned' => 'En colección', 'wishlist' => 'Lista de deseos', 'sold' => 'Vendido'];
                        @endphp
                        @if($game->status && isset($statusLabels[$game->status]))
                            <span class="text-slate-600">·</span>
                            <span class="text-slate-400">{{ $statusLabels[$game->status] }}</span>
                        @endif
                    </div>
                </div>
            </div>

            <div class="mt-8 pt-6 border-t border-slate-800 grid grid-cols-2 sm:grid-cols-3 gap-x-4 gap-y-5">
                <div>
                    <div class="{{ $label }}">EAN</div>
                    <div class="{{ $value }}">{{ $game->ean ?? '—' }}</div>
                </div>
                <div>
                    <div class="{{ $label }}">Desarrollador</div>
                    <div class="{{ $value }}">{{ $game->developer ?? '—' }}</div>
                </div>
                <div>
                    <div class="{{ $label }}">Fecha de lanzamiento</div>
                    <div class="{{ $value }}">{{ $game->release_date?->format('d/m/Y') ?? '—' }}</div>
                </div>
                <div>
                    <div class="{{ $label }}">Géneros</div>
                    <div class="{{ $value }}">{{ $game->genres ? implode(', ', $game->genres) : '—' }}</div>
                </div>
                <div>
                    <div class="{{ $label }}">Región</div>
                    <div class="{{ $value }}">{{ $game->region ?? '—' }}</div>
                </div>
                <div>
                    <div class="{{ $label }}">Clasificación por edad</div>
                    <div class="{{ $value }}">{{ $game->age_rating ?? '—' }}</div>
                </div>
                <div>
                    <div class="{{ $label }}">Manual</div>
                    <div class="{{ $value }}">
                        {{ ['included' => 'Con manual', 'missing' => 'Sin manual', 'booklet' => 'Folleto'][$game->manual_status] ?? '—' }}
                    </div>
                </div>
                <div>
                    <div class="{{ $label }}">Lugar de compra</div>
                    <div class="{{ $value }}">{{ $game->purchase_place ?? '—' }}</div>
                </div>
                <div>
                    <div class="{{ $label }}">Fecha de compra</div>
                    <div class="{{ $value }}">{{ $game->purchase_date?->format('d/m/Y') ?? '—' }}</div>
                </div>
            </div>

            @if($game->notes)
                <div class="mt-6 pt-6 border-t border-slate-800">
                    <div class="{{ $label }}">Notas</div>
                    <p class="text-sm text-slate-300 mt-1.5 whitespace-pre-line">{{ $game->notes }}</p>
                </div>
            @endif
        </div>
    </div>
@endsection
