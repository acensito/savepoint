<?php

namespace Tests\Unit\Models;

use App\Models\Game;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Aparte de GameTest (PHPUnit\Framework\TestCase, sin arrancar el framework
 * a propósito, más rápido): Game::ageRatingBadge() usa public_path()/asset(),
 * que necesitan el contenedor de Laravel ya arrancado (ver Tests\TestCase).
 */
class GameAgeRatingBadgeTest extends TestCase
{
    public function test_returns_null_without_an_age_rating(): void
    {
        $game = new Game(['age_rating' => null]);

        $this->assertNull($game->ageRatingBadge());
    }

    public function test_returns_null_for_a_blank_age_rating(): void
    {
        $game = new Game(['age_rating' => '   ']);

        $this->assertNull($game->ageRatingBadge());
    }

    /**
     * Los 4 sistemas, en su formato canónico "SISTEMA VALOR" (el que escribe
     * IgdbGameMatcher/IgdbController::apply() y el que ofrece el desplegable
     * del formulario) — con icono real ya colocado en
     * public/images/age-ratings/, así que iconPath no es null.
     */
    #[DataProvider('recognizedRatingsWithIcon')]
    public function test_recognizes_the_canonical_format_and_resolves_its_icon(string $ageRating, string $organization, string $value, string $severity): void
    {
        $game = new Game(['age_rating' => $ageRating]);

        $badge = $game->ageRatingBadge();

        $this->assertSame($organization, $badge['organization']);
        $this->assertSame($value, $badge['value']);
        $this->assertSame("{$organization} {$value}", $badge['label']);
        $this->assertSame($severity, $badge['severity']);
        $this->assertNotNull($badge['iconPath']);
    }

    public static function recognizedRatingsWithIcon(): array
    {
        return [
            'PEGI 3, verde' => ['PEGI 3', 'PEGI', '3', 'green'],
            'PEGI 12, ámbar' => ['PEGI 12', 'PEGI', '12', 'amber'],
            'PEGI 16, naranja' => ['PEGI 16', 'PEGI', '16', 'orange'],
            'PEGI 18, rojo' => ['PEGI 18', 'PEGI', '18', 'red'],
            'USK 0, verde' => ['USK 0', 'USK', '0', 'green'],
            'USK 18, rojo' => ['USK 18', 'USK', '18', 'red'],
            'CERO A, verde' => ['CERO A', 'CERO', 'A', 'green'],
            'CERO Z, rojo' => ['CERO Z', 'CERO', 'Z', 'red'],
            'ESRB E, verde' => ['ESRB E', 'ESRB', 'E', 'green'],
            // AO ("Adults Only"): caso especial de fichero, ver
            // Game::ageRatingIconFilename() -> ESRB_A.svg, no ESRB_AO.svg.
            'ESRB AO, rojo' => ['ESRB AO', 'ESRB', 'AO', 'red'],
        ];
    }

    /**
     * Formato tolerante: sin espacio, con guión, minúsculas — tiene que
     * reconocerse igual que el formato canónico (importado por CSV o escrito
     * a mano de formas distintas, ver issue #46).
     */
    #[DataProvider('tolerantFormats')]
    public function test_recognizes_tolerant_formats(string $ageRating, string $organization, string $value): void
    {
        $game = new Game(['age_rating' => $ageRating]);

        $badge = $game->ageRatingBadge();

        $this->assertSame($organization, $badge['organization']);
        $this->assertSame($value, $badge['value']);
    }

    public static function tolerantFormats(): array
    {
        return [
            'sin espacio' => ['PEGI12', 'PEGI', '12'],
            'con guión' => ['PEGI-12', 'PEGI', '12'],
            'minúsculas' => ['pegi 12', 'PEGI', '12'],
            'mayúsculas mezcladas' => ['Pegi 12', 'PEGI', '12'],
            'esrb con más' => ['esrb e10+', 'ESRB', 'E10+'],
        ];
    }

    /**
     * ESRB RP/EC no tienen icono todavía (ver issue #46, "iconos ya
     * colocados") — badge con color pero sin iconPath, no neutro: sigue
     * siendo una clasificación reconocida, solo le falta el fichero.
     */
    public function test_a_recognized_value_without_an_uploaded_icon_falls_back_to_a_colored_pill(): void
    {
        $game = new Game(['age_rating' => 'ESRB RP']);

        $badge = $game->ageRatingBadge();

        $this->assertSame('ESRB', $badge['organization']);
        $this->assertSame('RP', $badge['value']);
        $this->assertSame('neutral', $badge['severity']);
        $this->assertNull($badge['iconPath']);
    }

    #[DataProvider('unrecognizedFormats')]
    public function test_unrecognized_text_falls_back_to_a_neutral_badge_showing_the_raw_text(string $raw): void
    {
        $game = new Game(['age_rating' => $raw]);

        $badge = $game->ageRatingBadge();

        $this->assertNull($badge['organization']);
        $this->assertNull($badge['value']);
        $this->assertSame($raw, $badge['label']);
        $this->assertSame('neutral', $badge['severity']);
        $this->assertNull($badge['iconPath']);
    }

    public static function unrecognizedFormats(): array
    {
        return [
            'sistema desconocido' => ['CLASS_IND 18'],
            'valor fuera de rango' => ['PEGI 99'],
            'texto libre de un CSV' => ['Recomendado +18'],
            'solo un número' => ['18'],
        ];
    }
}
