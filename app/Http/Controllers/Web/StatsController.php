<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\Platform;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class StatsController extends Controller
{
    /**
     * Cuánto se conserva la caché como red de seguridad si algo mutara un
     * juego sin pasar por GameObserver (ver cacheKey()) — en el uso normal
     * se invalida antes de esto, así que el valor solo importa si ese caso
     * llegara a darse.
     */
    private const CACHE_TTL_MINUTES = 15;

    public function index(): View
    {
        $userId = auth()->id();

        $stats = Cache::remember(
            self::cacheKey($userId),
            now()->addMinutes(self::CACHE_TTL_MINUTES),
            fn () => $this->buildStats($userId),
        );

        return view('stats.index', [
            'totalGames' => $stats['totalGames'],
            'totalSpent' => $stats['totalSpent'],
            'averageRating' => $stats['averageRating'],
            'byPlatform' => $this->hydrateByPlatform($stats['byPlatform']),
            'byPlayStatus' => $stats['byPlayStatus'],
            'byStatus' => $stats['byStatus'],
            'spendingByMonth' => $stats['spendingByMonth'],
            'topGenres' => $stats['topGenres'],
            'byDecade' => $stats['byDecade'],
            'mostExpensive' => $stats['mostExpensiveId'] ? Game::with('platform')->find($stats['mostExpensiveId']) : null,
            'topRated' => $stats['topRatedId'] ? Game::with('platform')->find($stats['topRatedId']) : null,
            'salesByYear' => $stats['salesByYear'],
        ]);
    }

    /**
     * Clave de caché de las estadísticas de un usuario — compartida con
     * GameObserver (invalidación al guardar/borrar/restaurar un juego) y con
     * las acciones en bloque de GameController que mutan varios juegos de
     * golpe con una query directa (Game::whereIn(...)->update()/delete()),
     * que no disparan eventos de Eloquent y por tanto no pasan por el
     * observer.
     */
    public static function cacheKey(int $userId): string
    {
        return "stats:{$userId}";
    }

    /**
     * ~9 queries de agregación sobre toda la colección: se cachean en
     * conjunto (ver index()) porque todas dependen de los mismos datos y se
     * invalidan a la vez.
     *
     * Solo se cachean datos planos (arrays/escalares), nunca Collections ni
     * modelos Eloquent: config('cache.serializable_classes') está a `false`
     * (protección de Laravel contra ataques de deserialización si se filtra
     * el APP_KEY), así que cualquier objeto cacheado volvería como
     * __PHP_Incomplete_Class al leerlo. Por eso byPlatform() guarda
     * platform_id en vez del modelo Platform, y aquí se guarda el id de
     * mostExpensive/topRated en vez del modelo Game — index() los hidrata
     * fuera de la caché.
     *
     * @return array<string, mixed>
     */
    private function buildStats(int $userId): array
    {
        $base = Game::where('user_id', $userId);

        $totalGames = (clone $base)->count();
        $totalSpent = (float) (clone $base)->sum('price_paid');
        $averageRating = (clone $base)->whereNotNull('rating')->avg('rating');

        $byPlatform = $this->byPlatform(clone $base);
        $byPlayStatus = $this->byPlayStatus(clone $base, $totalGames);
        $byStatus = $this->byOwnershipStatus(clone $base, $totalGames);
        $spendingByMonth = $this->spendingByMonth(clone $base);
        $topGenres = $this->topGenres(clone $base);
        $byDecade = $this->byDecade(clone $base);
        $mostExpensiveId = (clone $base)->whereNotNull('price_paid')->orderByDesc('price_paid')->value('id');
        $topRatedId = (clone $base)->whereNotNull('rating')->orderByDesc('rating')->orderByDesc('id')->value('id');
        $salesByYear = $this->salesByYear($userId);

        return compact(
            'totalGames', 'totalSpent', 'averageRating', 'byPlatform', 'byPlayStatus', 'byStatus',
            'spendingByMonth', 'topGenres', 'byDecade', 'mostExpensiveId', 'topRatedId', 'salesByYear',
        );
    }

    /**
     * Convierte los platform_id planos de byPlatform() en modelos Platform
     * reales (fuera de la caché, ver buildStats()), con una sola query.
     *
     * @param  array<int, array{platform_id: int|null, total: int, percent: float}>  $rows
     * @return array<int, array{platform: Platform|null, total: int, percent: float}>
     */
    private function hydrateByPlatform(array $rows): array
    {
        $platformIds = collect($rows)->pluck('platform_id')->filter()->values();
        $platforms = Platform::whereIn('id', $platformIds)->get()->keyBy('id');

        return array_map(fn (array $row) => [
            'platform' => $row['platform_id'] ? $platforms->get($row['platform_id']) : null,
            'total' => $row['total'],
            'percent' => $row['percent'],
        ], $rows);
    }

    /**
     * Nº de juegos por plataforma, ordenado de mayor a menor.
     *
     * @param  Builder<Game>  $base
     * @return array<int, array{platform_id: int|null, total: int, percent: float}>
     */
    private function byPlatform(Builder $base): array
    {
        $counts = $base->selectRaw('platform_id, count(*) as total')
            ->groupBy('platform_id')
            ->pluck('total', 'platform_id');

        $max = $counts->max() ?: 1;

        return $counts->map(fn ($total, $platformId) => [
            'platform_id' => $platformId !== '' ? (int) $platformId : null,
            'total' => (int) $total,
            'percent' => round($total / $max * 100),
        ])->sortByDesc('total')->values()->all();
    }

    /**
     * Reparto por estado de juego (pendiente/jugando/terminado), con su % sobre el total.
     *
     * @param  Builder<Game>  $base
     * @return array<int, array{label: string, color: string, total: int, percent: float}>
     */
    private function byPlayStatus(Builder $base, int $total): array
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
     * Reparto por propiedad (en posesión/lista de deseos), con su % sobre el
     * total. No incluye "vendido": un juego vendido (ver
     * SalesController::markAsSold) es un borrado blando, así que nunca
     * aparece aquí — su propio reparto por año vive en salesByYear().
     *
     * @param  Builder<Game>  $base
     * @return array<int, array{label: string, color: string, total: int, percent: float}>
     */
    private function byOwnershipStatus(Builder $base, int $total): array
    {
        $counts = $base->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $labels = ['owned' => 'En colección', 'wishlist' => 'Lista de deseos'];
        $colors = ['owned' => '#818cf8', 'wishlist' => '#fbbf24'];

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
            'percent' => $total > 0 ? round(($key === '__unspecified' ? $unspecified : $counts->get($key,
                0)) / $total * 100) : 0,
        ])->values()->all();
    }

    /**
     * Gasto por mes de compra (últimos 12 meses con algún dato), para ver la
     * evolución en vez de solo el total acumulado. Se agrupa en PHP (no con
     * una función de fecha en SQL tipo to_char/strftime) para que funcione
     * igual en Postgres (producción) y SQLite (tests).
     *
     * @param  Builder<Game>  $base
     * @return array<int, array{label: string, total: float, percent: float}>
     */
    private function spendingByMonth(Builder $base): array
    {
        $grouped = $base->whereNotNull('purchase_date')
            ->get(['purchase_date', 'price_paid'])
            ->groupBy(fn (Game $game) => $game->purchase_date->format('Y-m'))
            ->map(fn ($games) => (float) $games->sum('price_paid'))
            ->sortKeys()
            ->take(-12);

        $max = (float) ($grouped->max() ?: 1);

        return $grouped->map(fn (float $total, string $month) => [
            'label' => Carbon::createFromFormat('Y-m-d', "{$month}-01")->startOfMonth()->translatedFormat('M Y'),
            'total' => $total,
            'percent' => $max > 0 ? round($total / $max * 100) : 0.0,
        ])->values()->all();
    }

    /**
     * Los géneros más repetidos en la colección (un juego puede tener
     * varios), de mayor a menor.
     *
     * @param  Builder<Game>  $base
     * @return array<int, array{genre: string, total: int, percent: float}>
     */
    private function topGenres(Builder $base): array
    {
        /** @var Collection<int, array<int, string>|null> $genresColumn */
        $genresColumn = $base->whereNotNull('genres')->pluck('genres');

        $counts = $genresColumn
            ->flatMap(fn ($genres) => $genres ?? [])
            ->filter()
            ->countBy()
            ->sortDesc()
            ->take(8);

        $max = $counts->max() ?: 1;

        return $counts->map(fn ($total, $genre) => [
            'genre' => (string) $genre,
            'total' => (int) $total,
            'percent' => round($total / $max * 100),
        ])->values()->all();
    }

    /**
     * Reparto por década de lanzamiento (años 90, 2000, 2010...), ordenado
     * cronológicamente (a diferencia de topGenres, que ordena por cantidad):
     * en una línea de tiempo importa más ver la evolución que el ranking.
     *
     * @param  Builder<Game>  $base
     * @return array<int, array{decade: string, total: int, percent: float}>
     */
    private function byDecade(Builder $base): array
    {
        $counts = $base->whereNotNull('release_date')
            ->pluck('release_date')
            ->countBy(fn ($date) => (int) (floor($date->year / 10) * 10))
            ->sortKeys();

        $max = $counts->max() ?: 1;

        return $counts->map(fn ($total, $decade) => [
            'decade' => "Años {$decade}",
            'total' => (int) $total,
            'percent' => round($total / $max * 100),
        ])->values()->all();
    }

    /**
     * Nº de ventas y rendimiento (precio de compra vs. precio de venta) por
     * año, para el bloque "Ventas por año". Mismo query que
     * SalesController::index() pero sin cargar relaciones (aquí solo hacen
     * falta los importes) y agrupado en PHP por el mismo motivo que
     * spendingByMonth()/byDecade(): funciona igual en Postgres (producción) y
     * SQLite (tests) sin depender de funciones de fecha propias de cada motor.
     *
     * @return array<int|string, array{count: int, paid: float, sold: float, profit: float, profit_percent: float|null}>
     */
    private function salesByYear(int $userId): array
    {
        $sales = Game::onlyTrashed()
            ->where('user_id', $userId)
            ->where('status', 'sold')
            ->whereNotNull('sold_at')
            ->get(['sold_at', 'price_paid', 'sale_price']);

        return $sales
            ->groupBy(fn (Game $game) => $game->sold_at->format('Y'))
            ->sortKeysDesc()
            ->map(function ($yearGames) {
                $paid = (float) $yearGames->sum('price_paid');
                $sold = (float) $yearGames->sum('sale_price');
                $profit = $sold - $paid;

                return [
                    'count' => (int) $yearGames->count(),
                    'paid' => $paid,
                    'sold' => $sold,
                    'profit' => $profit,
                    'profit_percent' => $paid > 0 ? round($profit / $paid * 100, 1) : null,
                ];
            })
            ->all();
    }
}
