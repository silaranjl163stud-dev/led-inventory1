<?php

namespace Tests\Unit;

use App\Support\SqlScript;
use PHPUnit\Framework\TestCase;

class SqlScriptTest extends TestCase
{
    public function test_statements_split_on_semicolons_outside_quoted_values(): void
    {
        $statements = SqlScript::statements(
            "-- comment;\nINSERT INTO repairs (reason) VALUES ('flicker; needs inspection');\nSELECT \"it; works\";"
        );

        $this->assertSame([
            "INSERT INTO repairs (reason) VALUES ('flicker; needs inspection')",
            'SELECT "it; works"',
        ], $statements);
    }

    public function test_statements_respect_escaped_quotes(): void
    {
        $statements = SqlScript::statements("INSERT INTO notes VALUES ('it\\'s; okay'); SELECT 1;");

        $this->assertSame([
            "INSERT INTO notes VALUES ('it\\'s; okay')",
            'SELECT 1',
        ], $statements);
    }
}
