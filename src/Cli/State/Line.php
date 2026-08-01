<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\State;

/**
 * A single line of a {@see StateFile}. The format is line-based — one value per line, one list entry
 * per line — so a value that arrives with newlines in it (a pasted condition, a multi-line testing
 * methodology) would read back as several. {@see flatten} is the ONE place that is prevented, stated
 * once so every state file is safe from it rather than each owner remembering.
 */
final class Line
{
    /**
     * $text as the single line the format stores: blank lines dropped, every remaining line trimmed
     * and joined with a space.
     */
    public static function flatten(string $text): string
    {
        $parts = [];

        foreach (explode("\n", str_replace("\r", "\n", $text)) as $part) {
            if (trim($part) !== '') {
                $parts[] = trim($part);
            }
        }

        return implode(' ', $parts);
    }
}
