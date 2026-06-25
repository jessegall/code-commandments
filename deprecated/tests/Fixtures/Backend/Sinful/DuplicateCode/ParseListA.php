<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Fixtures\Backend\Sinful\DuplicateCode;

/**
 * #202: shares ONLY the generic "parse a maybe-array" preamble with ParseListB,
 * then diverges entirely inside the loop. Must NOT be flagged.
 */
class ParseListA
{
    public function parseList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $acc = [];

        foreach ($value as $row) {
            if (is_array($row)) {
                $acc[] = $row['field'] ?? null;
            }
        }

        return $acc;
    }
}
