@extends('layouts.app')

@php
    $details = [
        ['icon' => 'tag', 'label' => 'EAN', 'value' => $game->ean],
        ['icon' => 'domain', 'label' => 'Desarrollador', 'value' => $game->developer],
        ['icon' => 'calendar_month', 'label' => 'Fecha de lanzamiento', 'value' => $game->release_date?->format('d/m/Y')],
        ['icon' => 'category', 'label' => 'Géneros', 'value' => $game->genres ? implode(', ', $game->genres) : null],
        ['icon' => 'public', 'label' => 'Región', 'value' => $game->region],
        ['icon' => 'badge', 'label' => 'Clasificación por edad', 'value' => $game->age_rating],
    ];

    $purchase = [
        ['icon' => 'menu_book', 'label' => 'Manual', 'value' => ['included' => 'Con manual', 'missing' => 'Sin manual', 'booklet' => 'Folleto'][$game->manual_status] ?? null],
        ['icon' => 'storefront', 'label' => 'Lugar de compra', 'value' => $game->purchase_place],
        ['icon' => 'event', 'label' => 'Fecha de compra', 'value' => $game->purchase_date?->format('d/m/Y')],
    ];
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

        <div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden">
            <div class="p-6 sm:p-8 bg-gradient-to-b from-slate-800/40 to-transparent">
                <div class="flex flex-col sm:flex-row gap-6">
                    <x-game-cover :game="$game" size="lg" class="!w-36 !rounded-2xl !text-4xl mx-auto sm:mx-0 flex-shrink-0 shadow-lg shadow-black/30" />

                    <div class="flex-1 min-w-0 flex flex-col justify-center">
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
            </div>

            <div class="px-6 sm:px-8 pb-6 sm:pb-8">
                <div class="pt-6 border-t border-slate-800">
                    <h2 class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-3">Detalles</h2>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                        @foreach($details as $field)
                            <div class="flex items-start gap-2.5 bg-slate-800/40 border border-slate-800 rounded-lg p-3">
                                <x-gicon name="{{ $field['icon'] }}" class="text-[18px] text-slate-500 mt-0.5 flex-shrink-0" />
                                <div class="min-w-0">
                                    <div class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider">{{ $field['label'] }}</div>
                                    <div class="text-sm text-slate-200 mt-0.5 break-words">{{ $field['value'] ?? '—' }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="mt-6 pt-6 border-t border-slate-800">
                    <h2 class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-3">Compra</h2>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                        @foreach($purchase as $field)
                            <div class="flex items-start gap-2.5 bg-slate-800/40 border border-slate-800 rounded-lg p-3">
                                <x-gicon name="{{ $field['icon'] }}" class="text-[18px] text-slate-500 mt-0.5 flex-shrink-0" />
                                <div class="min-w-0">
                                    <div class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider">{{ $field['label'] }}</div>
                                    <div class="text-sm text-slate-200 mt-0.5 break-words">{{ $field['value'] ?? '—' }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                @if($game->notes)
                    <div class="mt-6 pt-6 border-t border-slate-800">
                        <h2 class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2 flex items-center gap-1.5">
                            <x-gicon name="sticky_note_2" class="text-[16px]" />
                            Notas
                        </h2>
                        <p class="text-sm text-slate-300 leading-relaxed whitespace-pre-line bg-slate-800/40 border border-slate-800 rounded-lg p-4">{{ $game->notes }}</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
