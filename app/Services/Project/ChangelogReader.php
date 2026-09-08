<?php

namespace App\Services\Project;

/**
 * Lee CHANGELOG.md directamente (issue #75, página "Acerca de"): sin fuente
 * estructurada aparte que mantener a mano en paralelo — el propio changelog
 * ya es la única fuente de verdad de "qué ha cambiado y cuándo". Frágil ante
 * un cambio de formato del fichero a propósito, pero el formato (cabecera
 * "## AAAA-MM-DD" + bullets "- **Título**: ...") lleva así desde el primer
 * commit del proyecto.
 */
class ChangelogReader
{
    /**
     * La entrada más reciente (primera sección "## " del fichero, que por
     * convención del propio CHANGELOG va siempre más reciente primero) y los
     * títulos en negrita de sus bullets, sin el resto de cada párrafo — de
     * sobra para un resumen de "últimas características", no para leer el
     * changelog entero desde aquí.
     *
     * @return array{date: string, items: array<int, string>}|null null si
     *                                                             el fichero no existe o no tiene ninguna cabecera reconocible.
     */
    public function latestEntry(?string $path = null): ?array
    {
        $path ??= base_path('CHANGELOG.md');

        if (! is_file($path)) {
            return null;
        }

        $contents = file_get_contents($path);

        if ($contents === false || ! preg_match('/^## ([^\n]+)\n(.*?)(?=\n## |\z)/ms', $contents, $section)) {
            return null;
        }

        preg_match_all('/^- \*\*(.+?)\*\*/m', $section[2], $items);

        return [
            'date' => trim($section[1]),
            'items' => $items[1],
        ];
    }
}
