<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $game->title }} — SavePoint</title>
    <style>
        /* Ver el comentario en games/print-collection.blade.php: misma razón
           para no extender layouts.app aquí (altura fija con scroll interno,
           tema oscuro por defecto — nada de eso vale para imprimir). */
        * { box-sizing: border-box; }
        body {
            font-family: -apple-system, 'Segoe UI', Roboto, Arial, sans-serif;
            color: #111827;
            background: #f3f4f6;
            margin: 0;
            padding: 2rem;
        }
        .toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            max-width: 40rem;
            margin: 0 auto 1rem;
        }
        .toolbar a { color: #4f46e5; text-decoration: none; font-size: 0.875rem; }
        .toolbar button {
            background: #4f46e5;
            color: #fff;
            border: none;
            border-radius: 0.5rem;
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
            cursor: pointer;
        }
        .sheet {
            max-width: 40rem;
            margin: 0 auto;
            background: #fff;
            border-radius: 0.75rem;
            padding: 2rem;
        }
        .header { display: flex; gap: 1.5rem; align-items: flex-start; }
        .cover { width: 8rem; height: auto; border-radius: 0.5rem; border: 1px solid #e5e7eb; flex-shrink: 0; }
        .cover-placeholder {
            width: 8rem; height: 8rem; border-radius: 0.5rem; border: 1px solid #e5e7eb;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.5rem; font-weight: bold; color: #9ca3af; flex-shrink: 0;
        }
        h1 { font-size: 1.375rem; margin: 0 0 0.5rem; }
        .chip {
            display: inline-block; font-size: 0.75rem; padding: 0.125rem 0.5rem;
            border: 1px solid #d1d5db; border-radius: 0.375rem; margin-right: 0.375rem;
        }
        .stars { color: #d97706; letter-spacing: 1px; }
        .price { color: #059669; font-weight: 600; }
        dl { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem 1.5rem; margin: 1.5rem 0 0; padding-top: 1.5rem; border-top: 1px solid #e5e7eb; }
        dt { font-size: 0.6875rem; text-transform: uppercase; letter-spacing: 0.03em; color: #6b7280; }
        dd { margin: 0.125rem 0 0; font-size: 0.875rem; }
        .notes { margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid #e5e7eb; font-size: 0.875rem; white-space: pre-line; }
        .meta { color: #6b7280; font-size: 0.75rem; margin-top: 2rem; }

        @media print {
            body { background: #fff; padding: 0; }
            .no-print { display: none !important; }
            .sheet { padding: 0; }
            @page { margin: 1.5cm; }
        }
    </style>
</head>
<body>
    <div class="toolbar no-print">
        <a href="{{ route('web.games.show', $game->id) }}">← Volver a la ficha</a>
        <button type="button" onclick="window.print()">Imprimir / Guardar como PDF</button>
    </div>

    <div class="sheet">
        <div class="header">
            @if($game->coverUrl())
                <img class="cover" src="{{ $game->coverUrl() }}" alt="Carátula">
            @else
                <div class="cover-placeholder">{{ $game->coverInitials() }}</div>
            @endif

            <div>
                <h1>{{ $game->title }}</h1>
                <p>
                    @if($game->platform)<span class="chip">{{ $game->platform->name }}</span>@endif
                    @if($game->edition)<span class="chip">{{ $game->edition->name }}</span>@endif
                </p>
                <p>
                    <span class="stars">{{ $game->rating ? str_repeat('★', $game->rating) . str_repeat('☆', 5 - $game->rating) : 'Sin conservación registrada' }}</span>
                    @if($game->price_paid !== null)
                        &nbsp;·&nbsp;<span class="price">{{ number_format($game->price_paid, 2, ',', '.') }} €</span>
                    @endif
                </p>
            </div>
        </div>

        @php
            $playStatusLabels = ['pending' => 'Pendiente', 'playing' => 'Jugando', 'finished' => 'Terminado'];
            $statusLabels = ['owned' => 'En colección', 'wishlist' => 'Lista de deseos', 'sold' => 'Vendido'];
            $manualLabels = ['included' => 'Con manual', 'missing' => 'Sin manual', 'booklet' => 'Folleto'];

            $fields = [
                'EAN' => $game->ean,
                'Desarrollador' => $game->developer,
                'Fecha de lanzamiento' => $game->release_date?->format('d/m/Y'),
                'Géneros' => $game->genres ? implode(', ', $game->genres) : null,
                'Región' => $game->region,
                'Clasificación por edad' => $game->age_rating,
                'Estado de juego' => $playStatusLabels[$game->play_status] ?? $game->play_status,
                'Propiedad' => $game->status ? ($statusLabels[$game->status] ?? $game->status) : null,
                'Manual' => $manualLabels[$game->manual_status] ?? null,
                'Lugar de compra' => $game->purchase_place,
                'Fecha de compra' => $game->purchase_date?->format('d/m/Y'),
            ];
        @endphp

        <dl>
            @foreach($fields as $label => $value)
                <div>
                    <dt>{{ $label }}</dt>
                    <dd>{{ $value ?? '—' }}</dd>
                </div>
            @endforeach
        </dl>

        @if($game->notes)
            <div class="notes">
                <dt style="text-transform: uppercase; font-size: 0.6875rem; letter-spacing: 0.03em; color: #6b7280;">Notas</dt>
                <p style="margin-top: 0.375rem;">{{ $game->notes }}</p>
            </div>
        @endif

        <p class="meta">Generado el {{ now()->format('d/m/Y H:i') }} desde SavePoint.</p>
    </div>
</body>
</html>
