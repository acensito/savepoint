<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Jobs\IdentifyMissingGameCovers;
use App\Models\Game;
use App\Models\Platform;
use App\Services\GameLookup\ExternalCoverDownloader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * "Identificar en bloque" (issue #128): busca carátula/EAN en el catálogo
 * externo para los juegos sin carátula de una plataforma, en segundo plano
 * (ver Jobs\IdentifyMissingGameCovers), y deja los candidatos encontrados en
 * una cola de revisión — nunca se aplican solos, el usuario confirma en
 * bloque cuáles le valen antes de guardar nada (ver confirm()).
 */
class GameAutoIdentifyController extends Controller
{
    public function __construct(private readonly ExternalCoverDownloader $coverDownloader) {}

    /**
     * Formulario de lanzamiento: solo ofrece plataformas con al menos un
     * juego sin carátula (lanzarlo sobre una plataforma ya completa no
     * encontraría nada que revisar).
     */
    public function create(): View
    {
        $pendingCounts = Game::where('user_id', auth()->id())
            ->whereNull('cover')
            ->selectRaw('platform_id, count(*) as count')
            ->groupBy('platform_id')
            ->pluck('count', 'platform_id');

        $platforms = Platform::whereIn('id', $pendingCounts->keys())
            ->orderBy('name')
            ->get()
            ->map(fn (Platform $platform) => ['platform' => $platform, 'count' => $pendingCounts[$platform->id]]);

        return view('games.auto-identify', compact('platforms'));
    }

    /**
     * Despacha el job para la plataforma elegida y redirige con el id del
     * lote en la sesión (mismo patrón que GameImportController::store()),
     * para que la vista empiece a sondear su estado.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'platform_id' => ['required', Rule::exists('platforms', 'id')],
        ]);

        $batchId = (string) Str::uuid();
        Cache::put(self::cacheKey($batchId), ['user_id' => auth()->id(), 'done' => false], self::cacheTtl());

        IdentifyMissingGameCovers::dispatch(auth()->id(), (int) $validated['platform_id'], $batchId);

        return redirect()->route('web.games.auto-identify')->with('batchId', $batchId);
    }

    /**
     * Sondeado por la vista mientras Jobs\IdentifyMissingGameCovers procesa
     * la plataforma elegida (ver initAutoIdentifyStatusPolling en app.js).
     */
    public function status(Request $request, string $batchId): JsonResponse
    {
        $status = Cache::get(self::cacheKey($batchId));

        if ($status === null || $status['user_id'] !== $request->user()->id) {
            return response()->json(['message' => 'Lote no encontrado.'], 404);
        }

        return response()->json(Arr::except($status, ['user_id']));
    }

    /**
     * Aplica solo los candidatos que el usuario ha dejado marcados en la
     * cola de revisión — el resto del lote se descarta sin más (una pasada
     * futura los vuelve a intentar, ver Jobs\IdentifyMissingGameCovers). La
     * carátula no se descarga hasta este momento, no al generar el
     * candidato: así no se guardan ficheros huérfanos de los que el usuario
     * termina rechazando.
     */
    public function confirm(Request $request, string $batchId): RedirectResponse
    {
        $status = Cache::get(self::cacheKey($batchId));

        if ($status === null || $status['user_id'] !== auth()->id()) {
            abort(404);
        }

        $validated = $request->validate([
            'game_ids' => ['array'],
            'game_ids.*' => ['integer'],
        ]);

        $candidatesByGameId = collect((array) $status['candidates'])->keyBy('game_id');
        $applied = 0;

        foreach (Arr::get($validated, 'game_ids', []) as $gameId) {
            $candidate = $candidatesByGameId->get((int) $gameId);
            $game = $candidate !== null
                ? Game::where('user_id', auth()->id())->find($candidate['game_id'])
                : null;

            if ($candidate === null || $game === null) {
                continue;
            }

            $cover = $this->coverDownloader->download($candidate['proposed_cover_url']);
            if ($cover === null) {
                continue;
            }

            $game->update([
                'cover' => $cover,
                'ean' => $game->ean ?? $candidate['proposed_ean'],
            ]);
            $applied++;
        }

        Cache::forget(self::cacheKey($batchId));

        return redirect()->route('web.games.auto-identify')->with(
            'success',
            $applied === 1 ? '1 carátula aplicada.' : "{$applied} carátulas aplicadas."
        );
    }

    /**
     * Compartida con Jobs\IdentifyMissingGameCovers, que escribe aquí los
     * candidatos encontrados.
     */
    public static function cacheKey(string $batchId): string
    {
        return "game-auto-identify:{$batchId}";
    }

    /**
     * Mismo TTL generoso que GameImportController::cacheTtl() y por la misma
     * razón: cubre un worker de cola momentáneamente parado o con backlog
     * sin que el sondeo devuelva un 404 indistinguible de un fallo real.
     */
    public static function cacheTtl(): \DateTimeInterface
    {
        return now()->addDay();
    }
}
