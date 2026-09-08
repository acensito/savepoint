<?php

namespace Tests\Unit\Services\Project;

use App\Services\Project\ChangelogReader;
use Tests\TestCase;

class ChangelogReaderTest extends TestCase
{
    private function writeFixture(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'changelog');
        file_put_contents($path, $contents);

        return $path;
    }

    public function test_returns_null_when_the_file_does_not_exist(): void
    {
        $reader = new ChangelogReader;

        $this->assertNull($reader->latestEntry(sys_get_temp_dir().'/no-existe-'.uniqid().'.md'));
    }

    public function test_reads_the_date_and_bullet_titles_of_the_first_section_only(): void
    {
        $path = $this->writeFixture(<<<'MD'
            # Changelog

            Intro que no debe colarse en el resultado.

            ## 2026-09-08

            - **Primer título**: resto de la explicación que no interesa aquí.
            - **Segundo título** (#75, con contexto entre paréntesis): más texto.

            ## 2026-09-01

            - **Título de una sección anterior**: no debe aparecer en el resultado.
            MD);

        $reader = new ChangelogReader;
        $entry = $reader->latestEntry($path);

        $this->assertSame('2026-09-08', $entry['date']);
        $this->assertSame(['Primer título', 'Segundo título'], $entry['items']);
    }

    public function test_returns_an_empty_items_list_when_the_latest_section_has_no_bullets(): void
    {
        $path = $this->writeFixture(<<<'MD'
            # Changelog

            ## 2026-09-08

            Sección sin bullets todavía.

            ## 2026-09-01

            - **No debe aparecer**: es de la sección anterior.
            MD);

        $reader = new ChangelogReader;
        $entry = $reader->latestEntry($path);

        $this->assertSame('2026-09-08', $entry['date']);
        $this->assertSame([], $entry['items']);
    }
}
