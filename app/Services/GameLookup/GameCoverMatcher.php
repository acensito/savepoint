<?php

namespace App\Services\GameLookup;

use App\Models\Game;
use Illuminate\Support\Str;

/**
 * Elige, de los resultados de un catálogo externo (ver GameLookupInterface),
 * cuál -si alguno- es un candidato razonable para autocompletar la
 * carátula/EAN de un juego sin carátula, usado por el identificado en bloque
 * por plataforma (ver Jobs\IdentifyMissingGameCovers, issue #128). Nunca
 * aplica nada por su cuenta: solo decide si hay un candidato lo bastante
 * fiable como para proponerlo en la cola de revisión, dejando la decisión
 * final de guardarlo al usuario.
 */
class GameCoverMatcher
{
    public function __construct(private readonly GameLookupInterface $lookup) {}

    /**
     * Un juego con EAN ya identifica una copia física exacta, sin la
     * ambigüedad de buscar solo por título (ver matchByTitle()): basta con
     * encontrar ese mismo EAN entre los resultados para tener la carátula
     * que le corresponde. Sin coincidencia exacta de EAN entre los
     * resultados, no se propone nada — no tiene sentido caer al título
     * cuando ya se conoce el identificador exacto del juego.
     */
    public function matchByEan(Game $game): ?GameLookupResult
    {
        if ($game->ean === null) {
            return null;
        }

        return collect($this->lookup->search($game->ean))
            ->first(fn (GameLookupResult $result) => $result->ean === $game->ean);
    }

    /**
     * Sin EAN, la búsqueda por título es ambigua por naturaleza: el mismo
     * título puede tener varias ediciones/plataformas en el catálogo
     * externo, cada una con su propia carátula y EAN — mismo problema de
     * fondo que el emparejamiento automático de IGDB (ver IgdbGameMatcher,
     * issue #50). Mismo desempate: puntúa título exacto y plataforma
     * coincidente, y si el primer y segundo puesto empatan a puntuación, no
     * hay forma fiable de elegir uno solo — se deja sin candidato antes que
     * arriesgar una carátula/EAN equivocados en un identificado en bloque.
     */
    public function matchByTitle(Game $game): ?GameLookupResult
    {
        $scored = collect($this->lookup->search($game->title))
            ->map(fn (GameLookupResult $result) => ['result' => $result, 'score' => $this->titleMatchScore($result, $game)])
            ->sortByDesc('score')
            ->values();

        $best = $scored->first();

        if ($best === null || $best['score'] === 0) {
            return null;
        }

        // CEX suele listar el mismo juego más de una vez (distinta condición/
        // SKU, mismo título y plataforma) — verificado en real con "Kameo" y
        // "Aliens: Colonial Marines", ambos listados dos veces en Xbox 360.
        // Un empate entre entradas con el mismo título normalizado no es una
        // ambigüedad real (es el mismo juego duplicado), a diferencia de un
        // empate entre títulos distintos (ahí sí, ver arriba del método).
        $topTied = $scored->filter(fn (array $entry) => $entry['score'] === $best['score']);

        $ambiguous = $topTied->pluck('result')
            ->map(fn (GameLookupResult $result) => $this->normalizeTitle($result->title))
            ->unique()
            ->count() > 1;

        if ($ambiguous) {
            return null;
        }

        // Entre duplicados del mismo juego, no todos traen carátula (visto
        // en real: alguna de las entradas de CEX se queda sin foto) — se
        // prefiere la que sí la tenga en vez de la primera que aparezca, que
        // de otro modo dejaría el candidato entero sin sentido.
        return ($topTied->first(fn (array $entry) => $entry['result']->coverUrl !== null) ?? $best)['result'];
    }

    private function titleMatchScore(GameLookupResult $result, Game $game): int
    {
        $score = 0;

        if ($this->normalizeTitle($result->title) === $this->normalizeTitle($game->title)) {
            $score += 2;
        }

        $platformName = $game->platform?->name;
        if ($platformName !== null && $platformName !== '' && $result->platform !== null
            && Str::contains(Str::lower($result->platform), Str::lower($platformName))) {
            $score += 1;
        }

        return $score;
    }

    /**
     * Mismo criterio que IgdbLookupService::normalizeTitleForComparison():
     * dos puntos y guiones se tratan como separadores de subtítulo, no como
     * parte del título — sin esto, "Aliens Colonial Marines" (como lo
     * escribe el usuario) nunca igualaría a "Aliens: Colonial Marines" (como
     * lo lista CEX) aunque sean el mismo juego.
     */
    private function normalizeTitle(string $title): string
    {
        $normalized = Str::lower(trim($title));
        $normalized = str_replace(['’', '‘', '´', '`', "'"], '', $normalized);
        $normalized = str_replace(['–', '—', '-', ':'], ' ', $normalized);

        return trim(preg_replace('/\s+/', ' ', $normalized) ?? $normalized);
    }
}
