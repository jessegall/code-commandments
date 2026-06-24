<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Fixtures\Backend\Sinful\DuplicateCode;

/**
 * #202: shares ONLY the generic "parse a maybe-array" preamble with ParseListA.
 */
class ParseListB
{
    public function controlOutputs(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $acc = [];

        foreach ($value as $entry) {
            if (is_string($entry)) {
                $acc[] = strtoupper($entry);
            }
        }

        return $acc;
    }
}
