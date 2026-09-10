<?php

namespace Tests\Unit\Services\Games;

use App\Services\Games\CsvFieldEscaper;
use PHPUnit\Framework\TestCase;

class CsvFieldEscaperTest extends TestCase
{
    public function test_leaves_a_plain_value_untouched(): void
    {
        $this->assertSame('Celeste', CsvFieldEscaper::escape('Celeste'));
    }

    public function test_quotes_a_value_containing_a_comma(): void
    {
        $this->assertSame('"Assassin\'s Creed, Chronicles"', CsvFieldEscaper::escape("Assassin's Creed, Chronicles"));
    }

    public function test_quotes_and_escapes_a_value_containing_double_quotes(): void
    {
        $this->assertSame('"Say ""hi"""', CsvFieldEscaper::escape('Say "hi"'));
    }

    /**
     * CWE-1236: un valor que empiece por =, +, -, @, tabulador o retorno de
     * carro se interpreta como fórmula al abrirlo en Excel/Sheets.
     */
    public function test_prefixes_a_formula_looking_value_with_an_apostrophe(): void
    {
        $this->assertSame("'=cmd|'/c calc'!A1", CsvFieldEscaper::escape("=cmd|'/c calc'!A1"));
        $this->assertSame("'+1234", CsvFieldEscaper::escape('+1234'));
        $this->assertSame("'-1234", CsvFieldEscaper::escape('-1234'));
        $this->assertSame("'@SUM(A1:A2)", CsvFieldEscaper::escape('@SUM(A1:A2)'));
    }

    public function test_does_not_flag_a_value_that_merely_contains_a_formula_character_later(): void
    {
        $this->assertSame('Price=19.99', CsvFieldEscaper::escape('Price=19.99'));
    }
}
