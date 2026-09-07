<?php

namespace Tests\Unit\Models;

use App\Models\Edition;
use PHPUnit\Framework\TestCase;

class EditionTest extends TestCase
{
    public function test_a_new_edition_defaults_to_physical_disc_without_hitting_the_database(): void
    {
        $edition = new Edition(['name' => 'Sin formato']);

        $this->assertSame(Edition::FORMAT_PHYSICAL_DISC, $edition->format);
    }

    public function test_format_label_and_icon_cover_every_known_format(): void
    {
        foreach (Edition::FORMATS as $format => $meta) {
            $edition = new Edition(['format' => $format]);

            $this->assertSame($meta['label'], $edition->formatLabel());
            $this->assertSame($meta['icon'], $edition->formatIcon());
        }
    }

    public function test_format_label_and_icon_fall_back_to_physical_disc_for_an_unknown_value(): void
    {
        // Cubre datos residuales de antes de #142 ('physical' ya no es una
        // clave válida de FORMATS) o cualquier otro valor inesperado.
        $edition = new Edition(['format' => 'physical']);

        $this->assertSame(Edition::FORMATS[Edition::FORMAT_PHYSICAL_DISC]['label'], $edition->formatLabel());
        $this->assertSame(Edition::FORMATS[Edition::FORMAT_PHYSICAL_DISC]['icon'], $edition->formatIcon());
    }
}
