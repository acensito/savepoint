<?php

namespace Tests\Unit\Services\GameLookup;

use App\Services\GameLookup\AgeRatingResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AgeRatingResolverTest extends TestCase
{
    private function ageRatings(): array
    {
        return [
            ['organization' => 'PEGI', 'value' => '18'],
            ['organization' => 'ESRB', 'value' => 'M'],
            ['organization' => 'CERO', 'value' => 'Z'],
            ['organization' => 'USK', 'value' => '18'],
        ];
    }

    #[DataProvider('regionsMappedToAnOrganization')]
    public function test_picks_the_organization_that_matches_the_games_region(string $region, string $expected): void
    {
        $resolver = new AgeRatingResolver;

        $this->assertSame($expected, $resolver->pick($this->ageRatings(), $region));
    }

    public static function regionsMappedToAnOrganization(): array
    {
        return [
            'PAL-ES -> PEGI' => ['PAL-ES', 'PEGI 18'],
            'PAL-EU -> PEGI' => ['PAL-EU', 'PEGI 18'],
            'PAL-UK -> PEGI' => ['PAL-UK', 'PEGI 18'],
            'PAL-FR -> PEGI' => ['PAL-FR', 'PEGI 18'],
            'PAL-IT -> PEGI' => ['PAL-IT', 'PEGI 18'],
            'PAL-DE -> USK' => ['PAL-DE', 'USK 18'],
            'NTSC-U -> ESRB' => ['NTSC-U', 'ESRB M'],
            'NTSC-J -> CERO' => ['NTSC-J', 'CERO Z'],
        ];
    }

    public function test_falls_back_to_pegi_esrb_cero_usk_order_without_a_recognized_region(): void
    {
        $resolver = new AgeRatingResolver;

        $this->assertSame('PEGI 18', $resolver->pick($this->ageRatings(), null));
        $this->assertSame('PEGI 18', $resolver->pick($this->ageRatings(), 'REGION-QUE-NO-EXISTE'));
    }

    public function test_fallback_order_skips_organizations_the_game_does_not_have(): void
    {
        $resolver = new AgeRatingResolver;

        $ageRatings = [
            ['organization' => 'CERO', 'value' => 'A'],
            ['organization' => 'USK', 'value' => '0'],
        ];

        // Sin región reconocida y sin PEGI/ESRB disponibles: el siguiente de
        // la lista de fallback que sí tenga es CERO.
        $this->assertSame('CERO A', $resolver->pick($ageRatings, null));
    }

    /**
     * La región preferida gana si está disponible, aunque no sea la primera
     * del orden de fallback (PEGI) — PAL-DE apunta a USK, no a PEGI, incluso
     * cuando el juego también trae PEGI.
     */
    public function test_preferred_region_organization_wins_over_the_fallback_order(): void
    {
        $resolver = new AgeRatingResolver;

        $this->assertSame('USK 18', $resolver->pick($this->ageRatings(), 'PAL-DE'));
    }

    /**
     * Si la región apunta a un organismo que el juego no tiene (raro, pero
     * posible: IGDB no siempre trae todas las regionales), se cae al orden
     * de fallback en vez de devolver null sin más.
     */
    public function test_falls_back_to_the_order_when_the_preferred_organization_is_missing(): void
    {
        $resolver = new AgeRatingResolver;

        $ageRatings = [['organization' => 'CERO', 'value' => 'Z']];

        $this->assertSame('CERO Z', $resolver->pick($ageRatings, 'PAL-ES'));
    }

    public function test_returns_null_without_any_age_ratings(): void
    {
        $resolver = new AgeRatingResolver;

        $this->assertNull($resolver->pick(null, 'PAL-ES'));
        $this->assertNull($resolver->pick([], 'PAL-ES'));
    }
}
