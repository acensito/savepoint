<?php

namespace App\Services\Games;

/**
 * Compartido por GameExportController::export() y
 * GameImportController::template() (issue #188, auditoría de mantenibilidad
 * del 2026-09-10): antes duplicado byte a byte en los dos, con un comentario
 * en el importador señalando al exportador como "la fuente de verdad" — la
 * clase de riesgo real de tenerlo en dos sitios (un futuro cambio que
 * corrija uno y se olvide del otro), no solo cosmética.
 */
class CsvFieldEscaper
{
    /**
     * CWE-1236 (CSV/formula injection): un valor que empiece por =, +, -, @,
     * tabulador o retorno de carro se interpreta como fórmula al abrir el
     * CSV en Excel/Sheets — justo lo que invita a hacer el flujo de
     * exportar/editar/reimportar. Anteponer un apóstrofo fuerza a la hoja de
     * cálculo a tratarlo como texto literal (mitigación estándar de OWASP);
     * Excel/Sheets lo retira solo al abrir el fichero, así que no se nota si
     * el CSV se edita ahí antes de reimportarlo. Si en cambio se reimporta
     * el CSV tal cual (sin pasar por una hoja de cálculo), ese apóstrofo sí
     * queda pegado al valor — coste aceptado del fix, y raro en la práctica
     * (un título que empiece literalmente por uno de estos caracteres).
     */
    public static function escape(string $value): string
    {
        if (preg_match('/^[=+\-@\t\r]/', $value) === 1) {
            $value = "'".$value;
        }

        if (str_contains($value, ',') || str_contains($value, '"') || str_contains($value, "\n")) {
            return '"'.str_replace('"', '""', $value).'"';
        }

        return $value;
    }
}
