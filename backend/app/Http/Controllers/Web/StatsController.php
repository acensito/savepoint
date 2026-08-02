<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\Platform;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class StatsController extends Controller
{
    public function index(): View
    {
        $base = Game::where('user_id', auth()->id());

        $totalGames = (clone $base)->count();
        $totalSpent = (float) (clone $base)->sum('price_paid');
        $averageRating = (clone $base)->whereNotNull('rating')->avg('rating');

        $byPlatform = $this->byPlatform(clone $base);
        $byPlayStatus = $this->byPlayStatus(clone $base, $totalGames);
        $byStatus = $this->byOwnershipStatus(clone $base, $totalGames);
        $spendingByMonth = $this->spendingByMonth(clone $base);
        $topGenres = $this->topGenres(clone $base);
        $mostExpensive = (clone $base)->whereNotNull('price_paid')->with('platform')->orderByDesc('price_paid')->first();
        $topRated = (clone $base)->whereNotNull('rating')->with('platform')->orderByDesc('rating')->orderByDesc('id')->first();

        return view('stats.index', compact(
            'totalGames', 'totalSpent', 'averageRating', 'byPlatform', 'byPlayStatus', 'byStatus',
            'spendingByMonth', 'topGenres', 'mostExpensive', 'topRated',
        ));
    }

    /**
     * Nº de juegos por plataforma, ordenado de mayor a menor.
     */
    private function byPlatform($base)
    {
        $counts = $base->selectRaw('platform_id, count(*) as total')
            ->groupBy('platform_id')
            ->pluck('total', 'platform_id');

        $platforms = Platform::whereIn('id', $counts->keys()->filter())->get()->keyBy('id');
        $max = $counts->max() ?: 1;

        return $counts->map(fn ($total, $platformId) => [
            'platform' => $platforms->get($platformId),
            'total' => $total,
            'percent' => round($total / $max * 100),
        ])->sortByDesc('total')->values();
    }

    /**
     * Reparto por estado de juego (pendiente/jugando/terminado), con su % sobre el total.
     */
    private function byPlayStatus($base, int $total): array
    {
        $counts = $base->selectRaw('play_status, count(*) as total')
            ->groupBy('play_status')
            ->pluck('total', 'play_status');

        $labels = ['pending' => 'Pendiente', 'playing' => 'Jugando', 'finished' => 'Terminado'];
        $colors = ['pending' => '#94a3b8', 'playing' => '#818cf8', 'finished' => '#34d399'];

        return collect($labels)->map(fn ($label, $key) => [
            'label' => $label,
            'color' => $colors[$key],
            'total' => $counts->get($key, 0),
            'percent' => $total > 0 ? round($counts->get($key, 0) / $total * 100) : 0,
        ])->values()->all();
    }

    /**
     * Reparto por propiedad (en posesión/lista de deseos/vendido), con su % sobre el total.
     */
    private function byOwnershipStatus($base, int $total): array
    {
        $counts = $base->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $labels = ['owned' => 'En colección', 'wishlist' => 'Lista de deseos', 'sold' => 'Vendido'];
        $colors = ['owned' => '#818cf8', 'wishlist' => '#fbbf24', 'sold' => '#94a3b8'];

        // 'status' es opcional en el formulario: los juegos sin valor asignado
        // se agrupan aparte para que el reparto siga sumando el 100% del total.
        // (pluck() convierte la clave null del group-by en '' al construir el array).
        $unspecified = $counts->get('', 0);
        if ($unspecified > 0) {
            $labels['__unspecified'] = 'Sin especificar';
            $colors['__unspecified'] = '#475569';
        }

        return collect($labels)->map(fn ($label, $key) => [
            'label' => $label,
            'color' => $colors[$key],
            'total' => $key === '__unspecified' ? $unspecified : $counts->get($key, 0),
            'percent' => $total > 0 ? round(($key === '__unspecified' ? $unspecified : $counts->get($key, 0)) / $total * 100) : 0,
        ])->values()->all();
    }

    /**
     * Gasto por mes de compra (últimos 12 meses con algún dato), para ver la
     * evolución en vez de solo el total acumulado. Se agrupa en PHP (no con
     * una función de fecha en SQL tipo to_char/strftime) para que funcione
     * igual en Postgres (producción) y SQLite (tests).
     */
    private function spendingByMonth($base)
    {
        $grouped = $base->whereNotNull('purchase_date')
            ->get(['purchase_date', 'price_paid'])
            ->groupBy(fn (Game $game) => $game->purchase_date->format('Y-m'))
            ->map(fn ($games) => (float) $games->sum('price_paid'))
            ->sortKeys()
            ->take(-12);

        $max = (float) ($grouped->max() ?: 1);

        return $grouped->map(fn (float $total, string $month) => [
            'label' => Carbon::createFromFormat('Y-m', $month)->translatedFormat('M Y'),
            'total' => $total,
            'percent' => $max > 0 ? round($total / $max * 100) : 0,
        ])->values();
    }

    /**
     * Los géneros más repetidos en la colección (un juego puede tener
     * varios), de mayor a menor.
     */
    private function topGenres($base)
    {
        $counts = $base->whereNotNull('genres')
            ->pluck('genres')
            ->flatMap(fn ($genres) => $genres ?? [])
            ->filter()
            ->countBy()
            ->sortDesc()
            ->take(8);

        $max = $counts->max() ?: 1;

        return $counts->map(fn ($total, $genre) => [
            'genre' => $genre,
            'total' => $total,
            'percent' => round($total / $max * 100),
        ])->values();
    }
}
