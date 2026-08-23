<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mi colección — SavePoint</title>
    <style>
        /*
         * Vista independiente del layout de la app (games/print-collection.blade.php):
         * a propósito no usa @@extends('layouts.app') ni las clases slate-* de
         * Tailwind. El @@ de arriba va escapado a propósito: Blade compila esa
         * directiva aunque aparezca solo mencionada en un comentario CSS como
         * este, no solo cuando se usa de verdad — sin escapar, acababa
         * renderizando el layout completo de la app pegado al final del
         * fichero, como una "página" extra con la interfaz y ningún juego al
         * imprimir/exportar a PDF. El layout de la app fija la altura al
         * viewport y mete el
         * contenido en un contenedor con scroll interno (pensado para una SPA
         * en pantalla), así que al imprimir solo saldría lo que cupiera en una
         * pantalla, no la colección entera. Aquí es HTML/CSS plano en flujo
         * normal, para que el navegador la reparta en tantas páginas como
         * haga falta. También evita depender del tema oscuro/claro de la app:
         * blanco y negro de toda la vida, para no gastar tinta imprimiendo un
         * fondo oscuro.
         */
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
            max-width: 60rem;
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
            max-width: 60rem;
            margin: 0 auto;
            background: #fff;
            border-radius: 0.75rem;
            padding: 2rem;
        }
        h1 { font-size: 1.5rem; margin: 0 0 0.25rem; }
        .meta { color: #6b7280; font-size: 0.8125rem; margin: 0 0 0.25rem; }
        .totals { font-size: 0.875rem; margin: 0.75rem 0 1.5rem; }
        table { width: 100%; border-collapse: collapse; font-size: 0.8125rem; }
        thead { display: table-header-group; }
        th, td { text-align: left; padding: 0.5rem 0.625rem; border-bottom: 1px solid #e5e7eb; }
        th { color: #6b7280; text-transform: uppercase; font-size: 0.6875rem; letter-spacing: 0.03em; }
        tr { break-inside: avoid; }
        .num { text-align: right; white-space: nowrap; }
        .stars { color: #d97706; letter-spacing: 1px; }
        .empty { color: #6b7280; padding: 2rem 0; text-align: center; }

        @media print {
            body { background: #fff; padding: 0; }
            .no-print { display: none !important; }
            .sheet { padding: 0; }
            @page { size: A4 landscape; margin: 1.5cm; }
        }
    </style>
</head>
<body>
    <div class="toolbar no-print">
        <a href="{{ route('web.games.index') }}">← Volver a mi colección</a>
        <button type="button" id="print-button">Imprimir / Guardar como PDF</button>
    </div>

    <div class="sheet">
        <h1>Mi colección — SavePoint</h1>
        <p class="meta">Generado el {{ now()->format('d/m/Y H:i') }}</p>

        @php
            $playStatusLabels = ['pending' => 'Pendiente', 'playing' => 'Jugando', 'finished' => 'Terminado'];
            $statusLabels = ['owned' => 'En colección', 'sold' => 'Vendido'];
            $filters = array_filter([
                $query !== '' ? "búsqueda «{$query}»" : null,
                $platform ? "plataforma {$platform->name}" : null,
                $playStatus !== '' ? 'estado ' . ($playStatusLabels[$playStatus] ?? $playStatus) : null,
                $status !== '' ? 'propiedad ' . ($statusLabels[$status] ?? $status) : null,
            ]);
        @endphp
        <p class="meta">{{ $filters ? 'Filtros aplicados: ' . implode(', ', $filters) . '.' : 'Colección completa, sin filtros.' }}</p>

        <p class="totals">
            <strong>{{ number_format($totals['count']) }}</strong> {{ \Illuminate\Support\Str::plural('juego', $totals['count']) }}
            &nbsp;·&nbsp;
            <strong>{{ number_format($totals['spent'], 2, ',', '.') }} €</strong> invertidos
        </p>

        @if($games->isEmpty())
            <p class="empty">No hay juegos que coincidan con estos filtros.</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Título</th>
                        <th>EAN</th>
                        <th>Plataforma</th>
                        <th>Edición</th>
                        <th>Conservación</th>
                        <th class="num">Precio</th>
                        <th>Fecha de compra</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($games as $game)
                        <tr>
                            <td>{{ $game->title }}</td>
                            <td>{{ $game->ean ?? '—' }}</td>
                            <td>{{ $game->platform?->name ?? '—' }}</td>
                            <td>{{ $game->edition?->name ?? '—' }}</td>
                            <td class="stars">{{ $game->rating ? str_repeat('★', $game->rating) . str_repeat('☆', 5 - $game->rating) : '—' }}</td>
                            <td class="num">{{ $game->price_paid !== null ? number_format($game->price_paid, 2, ',', '.') . ' €' : '—' }}</td>
                            <td>{{ $game->purchase_date?->format('d/m/Y') ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <script nonce="{{ $cspNonce }}">
        document.getElementById('print-button')?.addEventListener('click', () => window.print());
    </script>
</body>
</html>
