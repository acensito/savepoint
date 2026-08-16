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
            <div class="relative p-6 sm:p-8 overflow-hidden">
                @if($game->backgroundUrl())
                    <div class="absolute inset-0 bg-cover bg-center" style="background-image: url('{{ $game->backgroundUrl() }}')"></div>
                    <div class="absolute inset-0 bg-gradient-to-b from-slate-950/75 via-slate-950/70 to-slate-900"></div>
                @else
                    <div class="absolute inset-0 bg-gradient-to-b from-slate-800/40 to-transparent"></div>
                @endif

                <div class="relative flex flex-col sm:flex-row sm:items-center gap-6">
                    <x-game-cover :game="$game" size="lg" class="!w-36 !rounded-2xl !text-4xl mx-auto sm:mx-0 flex-shrink-0 shadow-lg shadow-black/30" />

                    <div class="flex-1 min-w-0 flex flex-col justify-center">
                        <h1 class="text-2xl font-bold text-slate-100 tracking-tight">{{ $game->title }}</h1>

                        <div class="mt-2 flex items-center gap-2 flex-wrap">
                            <x-platform-chip :platform="$game->platform" />
                            @if($game->edition)
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-xs font-medium bg-slate-800 text-slate-300 border border-slate-700">
                                    <x-gicon :name="$game->edition->formatIcon()" :title="$game->edition->formatLabel()" class="text-[13px] text-slate-400" />
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

                            @if($game->for_sale)
                                <span class="text-slate-600">·</span>
                                <span class="inline-flex items-center gap-1 text-amber-400">
                                    <x-gicon name="sell" class="text-[14px]" />
                                    En venta
                                </span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="px-6 sm:px-8 pb-6 sm:pb-8">
                <div class="pt-6 border-t border-slate-800">
                    <h2 class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-3">Detalles</h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
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
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
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

                <div class="mt-6 pt-6 border-t border-slate-800">
                    <div class="flex items-center justify-between gap-3 mb-3">
                        <h2 class="text-xs font-semibold text-slate-500 uppercase tracking-wider flex items-center gap-1.5">
                            <x-gicon name="travel_explore" class="text-[16px]" />
                            IGDB
                        </h2>
                        <button type="button" id="igdb-search-trigger"
                            class="text-xs font-medium text-indigo-400 hover:text-indigo-300">
                            {{ $game->igdb_id ? 'Corregir coincidencia' : 'Buscar en IGDB' }}
                        </button>
                    </div>

                    @if($game->igdb_genres || $game->igdb_rating !== null)
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            @if($game->igdb_genres)
                                <div class="flex items-start gap-2.5 bg-slate-800/40 border border-slate-800 rounded-lg p-3">
                                    <x-gicon name="category" class="text-[18px] text-slate-500 mt-0.5 flex-shrink-0" />
                                    <div class="min-w-0">
                                        <div class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Géneros (IGDB)</div>
                                        <div class="text-sm text-slate-200 mt-0.5 break-words">{{ implode(', ', $game->igdb_genres) }}</div>
                                    </div>
                                </div>
                            @endif
                            @if($game->igdb_rating !== null)
                                <div class="flex items-start gap-2.5 bg-slate-800/40 border border-slate-800 rounded-lg p-3">
                                    <x-gicon name="star_rate" class="text-[18px] text-slate-500 mt-0.5 flex-shrink-0" />
                                    <div class="min-w-0">
                                        <div class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Nota IGDB</div>
                                        <div class="text-sm text-slate-200 mt-0.5">{{ number_format((float) $game->igdb_rating, 0) }}/100</div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    @else
                        <p class="text-sm text-slate-500">
                            {{ $game->igdb_matched_at ? 'Sin coincidencia en IGDB todavía.' : 'Buscando en IGDB…' }}
                        </p>
                    @endif

                    <p id="igdb-search-status" class="hidden text-xs text-slate-500 mt-2"></p>

                    <div id="igdb-search-box" class="hidden mt-2 flex gap-1.5">
                        <input type="text" id="igdb-search-input" placeholder="Buscar otro título…" value="{{ $game->title }}"
                            class="flex-1 min-w-0 rounded-lg border border-slate-700 bg-slate-800 text-slate-100 text-xs px-2.5 py-1.5 focus:border-indigo-500 focus:ring-indigo-500 outline-none">
                        <button type="button" id="igdb-search-btn"
                            class="flex-shrink-0 text-xs font-medium text-indigo-400 hover:text-indigo-300 px-2">Buscar</button>
                    </div>

                    <ul id="igdb-search-results" class="hidden mt-1.5 space-y-1 max-h-56 overflow-y-auto rounded-lg border border-slate-700 bg-slate-800/50 p-1.5"></ul>

                    <!-- Sin JS: solo lleva los campos del resultado que se elija en
                         igdb-search-results, rellenados por script antes de enviar. -->
                    <form id="igdb-apply-form" action="{{ route('web.games.igdb-apply', $game) }}" method="POST" class="hidden">
                        @csrf
                        <input type="hidden" name="igdb_id">
                        <input type="hidden" name="developer">
                        <input type="hidden" name="release_date">
                        <input type="hidden" name="rating">
                    </form>
                </div>

                <div class="mt-6 pt-6 border-t border-slate-800">
                    <div class="flex items-center justify-between gap-3 mb-3">
                        <h2 class="text-xs font-semibold text-slate-500 uppercase tracking-wider flex items-center gap-1.5">
                            <x-gicon name="wallpaper" class="text-[16px]" />
                            Fondo
                        </h2>
                        <div class="flex items-center gap-3">
                            @if($game->igdb_background)
                                <button type="button" id="igdb-background-clear" class="text-xs font-medium text-slate-500 hover:text-red-400">Quitar</button>
                            @endif
                            <button type="button" id="igdb-background-trigger" class="text-xs font-medium text-indigo-400 hover:text-indigo-300">
                                Elegir fondo
                            </button>
                        </div>
                    </div>

                    <p class="text-sm text-slate-500">
                        Nunca se aplica solo: eliges tú de entre las opciones de IGDB, o lo dejas sin fondo.
                    </p>

                    <p id="igdb-background-status" class="hidden text-xs text-slate-500 mt-2"></p>

                    <div id="igdb-background-results" class="hidden mt-2 grid grid-cols-2 sm:grid-cols-4 gap-2"></div>

                    <form id="igdb-background-form" action="{{ route('web.games.igdb-background', $game) }}" method="POST" class="hidden">
                        @csrf
                        <input type="hidden" name="image_id">
                    </form>
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

                <!-- Al final de la ficha a propósito (no en la cabecera, junto a
                     Editar/Borrar): con 4 botones ahí la cabecera se deformaba en
                     móvil y quedaba una zona demasiado cargada para lo poco
                     habitual que es esta acción. -->
                @if($game->status === 'owned')
                    <div class="mt-6 pt-6 border-t border-slate-800">
                        <h2 class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-3 flex items-center gap-1.5">
                            <x-gicon name="sell" class="text-[16px]" />
                            Venta
                        </h2>
                        <div class="flex flex-wrap items-center gap-2">
                            <form action="{{ route('web.games.quick-update', $game->id) }}" method="POST">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="for_sale" value="{{ $game->for_sale ? 0 : 1 }}">
                                <button type="submit"
                                    class="flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm font-medium border transition-colors {{ $game->for_sale ? 'bg-amber-500/10 border-amber-500/40 text-amber-300 hover:bg-amber-500/20' : 'border-slate-700 text-slate-400 hover:bg-slate-800 hover:text-slate-100' }}">
                                    <x-gicon name="sell" class="text-[16px]" />
                                    {{ $game->for_sale ? 'Quitar de en venta' : 'Marcar en venta' }}
                                </button>
                            </form>

                            <button type="button" id="mark-sold-trigger"
                                class="flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm font-medium border border-slate-700 text-slate-400 hover:bg-slate-800 hover:text-emerald-400 transition-colors">
                                <x-gicon name="paid" class="text-[16px]" />
                                Marcar como vendido
                            </button>
                        </div>

                        <div id="mark-sold-panel" class="{{ $errors->has('sale_price') || $errors->has('sold_at') ? '' : 'hidden' }} mt-3 bg-slate-800/40 border border-slate-800 rounded-lg p-4">
                            <h3 class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-3">Marcar «{{ $game->title }}» como vendido</h3>
                            <form action="{{ route('web.games.mark-sold', $game->id) }}" method="POST" class="space-y-3">
                                @csrf
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div>
                                        <label for="sale_price" class="block text-xs font-medium text-slate-400 mb-1">Precio de venta</label>
                                        <input type="number" step="0.01" min="0" name="sale_price" id="sale_price" value="{{ old('sale_price') }}" required
                                            class="w-full rounded-lg border border-slate-700 bg-slate-800 text-slate-100 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500 outline-none">
                                        @error('sale_price') <span class="text-xs text-red-400">{{ $message }}</span> @enderror
                                    </div>
                                    <div>
                                        <label for="sold_at" class="block text-xs font-medium text-slate-400 mb-1">Fecha de venta</label>
                                        <input type="date" name="sold_at" id="sold_at" value="{{ old('sold_at', now()->toDateString()) }}" required
                                            class="w-full rounded-lg border border-slate-700 bg-slate-800 text-slate-100 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500 outline-none">
                                        @error('sold_at') <span class="text-xs text-red-400">{{ $message }}</span> @enderror
                                    </div>
                                </div>
                                <div>
                                    <label for="sale_notes" class="block text-xs font-medium text-slate-400 mb-1">Notas</label>
                                    <textarea name="notes" id="sale_notes" rows="2"
                                        class="w-full rounded-lg border border-slate-700 bg-slate-800 text-slate-100 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500 outline-none">{{ old('notes', $game->notes) }}</textarea>
                                </div>
                                <div class="flex items-center gap-2">
                                    <button type="submit" class="bg-emerald-600 hover:bg-emerald-500 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                                        Confirmar venta
                                    </button>
                                    <button type="button" id="mark-sold-cancel" class="text-sm text-slate-400 hover:text-slate-100 px-2">Cancelar</button>
                                </div>
                            </form>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <script>
        (function () {
            const trigger = document.getElementById('igdb-search-trigger');
            if (!trigger) return;

            const box = document.getElementById('igdb-search-box');
            const input = document.getElementById('igdb-search-input');
            const searchBtn = document.getElementById('igdb-search-btn');
            const statusEl = document.getElementById('igdb-search-status');
            const resultsEl = document.getElementById('igdb-search-results');
            const applyForm = document.getElementById('igdb-apply-form');
            const searchUrl = '{{ route('web.games.igdb-search', $game) }}';

            trigger.addEventListener('click', () => {
                box.classList.remove('hidden');
                input.focus();
                input.select();
                search(input.value.trim());
            });

            searchBtn.addEventListener('click', () => search(input.value.trim()));
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    search(input.value.trim());
                }
            });

            async function search(query) {
                searchBtn.disabled = true;
                resultsEl.classList.add('hidden');
                resultsEl.innerHTML = '';
                statusEl.classList.remove('hidden');
                statusEl.textContent = 'Buscando en IGDB…';

                try {
                    const url = query ? `${searchUrl}?q=${encodeURIComponent(query)}` : searchUrl;
                    const response = await fetch(url, { headers: { 'Accept': 'application/json' } });
                    if (!response.ok) throw new Error('request failed');
                    const { results } = await response.json();

                    if (!results.length) {
                        statusEl.textContent = 'Sin resultados en IGDB. Prueba con otras palabras del título.';
                        return;
                    }

                    statusEl.classList.add('hidden');
                    resultsEl.innerHTML = results.map((r, i) => `
                        <li>
                            <button type="button" class="js-igdb-pick w-full text-left p-1.5 rounded-lg hover:bg-slate-700" data-index="${i}">
                                <span class="block text-xs text-slate-200 truncate">${r.title}${r.platforms ? ` <span class="text-slate-500">— ${r.platforms}</span>` : ''}</span>
                                <span class="block text-[10px] text-slate-500 truncate">
                                    ${r.developer ?? 'Sin desarrollador'} · ${r.release_date ?? 'sin fecha'}${r.genres ? ` · ${r.genres.join(', ')}` : ''}
                                </span>
                            </button>
                        </li>
                    `).join('');
                    resultsEl.classList.remove('hidden');

                    resultsEl.querySelectorAll('.js-igdb-pick').forEach((el) => {
                        el.addEventListener('click', () => applyResult(results[Number(el.dataset.index)]));
                    });
                } catch (err) {
                    statusEl.classList.remove('hidden');
                    statusEl.textContent = 'No se pudo buscar en IGDB. Comprueba tu conexión e inténtalo de nuevo.';
                } finally {
                    searchBtn.disabled = false;
                }
            }

            function applyResult(result) {
                applyForm.querySelector('[name="igdb_id"]').value = result.igdb_id;
                applyForm.querySelector('[name="developer"]').value = result.developer ?? '';
                applyForm.querySelector('[name="release_date"]').value = result.release_date ?? '';
                applyForm.querySelector('[name="rating"]').value = result.rating ?? '';

                // Nº de géneros variable: se quitan los de un envío anterior (si
                // el usuario buscó, aplicó y luego corrigió otra vez) antes de
                // añadir los del resultado elegido.
                applyForm.querySelectorAll('input[name="genres[]"]').forEach((el) => el.remove());
                (result.genres ?? []).forEach((genre) => {
                    const genreInput = document.createElement('input');
                    genreInput.type = 'hidden';
                    genreInput.name = 'genres[]';
                    genreInput.value = genre;
                    applyForm.appendChild(genreInput);
                });

                applyForm.submit();
            }
        })();

        (function () {
            const trigger = document.getElementById('igdb-background-trigger');
            if (!trigger) return;

            const clearBtn = document.getElementById('igdb-background-clear');
            const statusEl = document.getElementById('igdb-background-status');
            const resultsEl = document.getElementById('igdb-background-results');
            const form = document.getElementById('igdb-background-form');
            const artworksUrl = '{{ route('web.games.igdb-artworks', $game) }}';

            trigger.addEventListener('click', async () => {
                trigger.disabled = true;
                resultsEl.classList.add('hidden');
                resultsEl.innerHTML = '';
                statusEl.classList.remove('hidden');
                statusEl.textContent = 'Buscando fondos en IGDB…';

                try {
                    const response = await fetch(artworksUrl, { headers: { 'Accept': 'application/json' } });
                    if (!response.ok) throw new Error('request failed');
                    const { results } = await response.json();

                    if (!results.length) {
                        statusEl.textContent = 'IGDB no tiene arte disponible para este juego.';
                        return;
                    }

                    statusEl.classList.add('hidden');
                    resultsEl.innerHTML = results.map((r, i) => `
                        <button type="button" class="js-igdb-background-pick aspect-video rounded-lg overflow-hidden border border-slate-700 hover:border-indigo-500 transition-colors" data-index="${i}">
                            <img src="${r.thumb_url}" alt="" class="w-full h-full object-cover">
                        </button>
                    `).join('');
                    resultsEl.classList.remove('hidden');

                    resultsEl.querySelectorAll('.js-igdb-background-pick').forEach((el) => {
                        el.addEventListener('click', () => {
                            form.querySelector('[name="image_id"]').value = results[Number(el.dataset.index)].image_id;
                            form.submit();
                        });
                    });
                } catch (err) {
                    statusEl.classList.remove('hidden');
                    statusEl.textContent = 'No se pudo buscar en IGDB. Comprueba tu conexión e inténtalo de nuevo.';
                } finally {
                    trigger.disabled = false;
                }
            });

            clearBtn?.addEventListener('click', () => {
                form.querySelector('[name="image_id"]').value = '';
                form.submit();
            });
        })();

        (function () {
            const trigger = document.getElementById('mark-sold-trigger');
            const panel = document.getElementById('mark-sold-panel');
            const cancelBtn = document.getElementById('mark-sold-cancel');
            if (!trigger || !panel) return;

            trigger.addEventListener('click', () => {
                panel.classList.remove('hidden');
                document.getElementById('sale_price')?.focus();
            });

            cancelBtn?.addEventListener('click', () => panel.classList.add('hidden'));
        })();
    </script>
@endsection
