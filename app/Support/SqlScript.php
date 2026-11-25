<?php

namespace App\Support;

use RuntimeException;

final class SqlScript
{
    public static function statements(string $sql): array
    {
        $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
        $statements = [];
        $current = '';
        $quote = null;
        $escaped = false;
        $length = strlen($sql);

        for ($index = 0; $index < $length; $index++) {
            $character = $sql[$index];

            if ($quote !== null) {
                $current .= $character;

                if ($escaped) {
                    $escaped = false;
                    continue;
                }

                if ($character === '\\') {
                    $escaped = true;
                    continue;
                }

                if ($character === $quote) {
                    if ($index + 1 < $length && $sql[$index + 1] === $quote) {
                        $current .= $sql[++$index];
                        continue;
                    }
                    $quote = null;
                }
                continue;
            }

            if ($character === "'" || $character === '"') {
                $quote = $character;
                $current .= $character;
                continue;
            }

            if ($character === ';') {
                $statement = trim($current);
                if ($statement !== '') {
                    $statements[] = $statement;
                }
                $current = '';
                continue;
            }

            $current .= $character;
        }

        if ($quote !== null) {
            throw new RuntimeException('SQL script contains an unterminated quoted value.');
        }

        $statement = trim($current);
        if ($statement !== '') {
            $statements[] = $statement;
        }

        return $statements;
    }
}
