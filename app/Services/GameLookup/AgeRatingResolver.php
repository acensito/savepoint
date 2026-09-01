<?php

namespace App\Services\GameLookup;

/**
 * Elige una única clasificación por edad entre las que IGDB devuelve para un
 * juego (puede traer varias regionales a la vez, ver
 * IgdbLookupService::search()), según la región física del juego —
 * criterio de la issue #46. Compartido por el match automático
 * (IgdbGameMatcher::matchIfNeeded()) y la corrección manual
 * (IgdbController::apply()), para no duplicar la tabla de prioridad entre
 * los dos sitios.
 */
class AgeRatingResolver
{
    /**
     * PAL-DE es el único preset de región que no mapea a PEGI: Alemania usa
     * USK, no PEGI, a diferencia del resto de PAL. NTSC-U/NTSC-J van a
     * ESRB/CERO respectivamente.
     */
    private const REGION_TO_ORGANIZATION = [
        'PAL-ES' => 'PEGI',
        'PAL-EU' => 'PEGI',
        'PAL-UK' => 'PEGI',
        'PAL-FR' => 'PEGI',
        'PAL-IT' => 'PEGI',
        'PAL-DE' => 'USK',
        'NTSC-U' => 'ESRB',
        'NTSC-J' => 'CERO',
    ];

    /**
     * Sin región reconocida (o el juego no tiene región puesta), orden de
     * fallback: el primero de estos organismos que IGDB haya devuelto.
     */
    private const FALLBACK_ORDER = ['PEGI', 'ESRB', 'CERO', 'USK'];

    /**
     * @param  array<int, array{organization: string, value: string}>|null  $ageRatings
     */
    public function pick(?array $ageRatings, ?string $region): ?string
    {
        if ($ageRatings === null || $ageRatings === []) {
            return null;
        }

        $byOrganization = collect($ageRatings)->keyBy('organization');

        $preferredOrganization = self::REGION_TO_ORGANIZATION[$region] ?? null;
        if ($preferredOrganization !== null && $byOrganization->has($preferredOrganization)) {
            return $this->format($byOrganization[$preferredOrganization]);
        }

        foreach (self::FALLBACK_ORDER as $organization) {
            if ($byOrganization->has($organization)) {
                return $this->format($byOrganization[$organization]);
            }
        }

        return null;
    }

    /**
     * @param  array{organization: string, value: string}  $entry
     */
    private function format(array $entry): string
    {
        return "{$entry['organization']} {$entry['value']}";
    }
}
