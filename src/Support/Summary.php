<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

use ReflectionClass;

/**
 * The one line a class says about itself — the first sentence of its docblock, `{@see …}` tags
 * reduced to their short name. The README tables and the journal plugin's settings both read it.
 */
final class Summary
{
    public static function of(object|string $subject): string
    {
        $doc = (new ReflectionClass($subject))->getDocComment();

        if ($doc === false) {
            return '';
        }

        $text = (string) preg_replace('#/\*\*|\*/|^\s*\*\s?#m', '', $doc);
        $text = (string) preg_replace_callback(
            '/\{@see\s+\\\\?([^}\s]+)\}/',
            static fn (array $m): string => (static fn (array $p): string => (string) end($p))(explode('\\', $m[1])),
            $text,
        );
        $text = trim((string) preg_replace('/\s+/', ' ', $text));

        // A period that ENDS a sentence — followed by whitespace then a capital, or the end of the
        // text — so mid-sentence abbreviations (`e.g.`, `i.e.`) never cut it short.
        return preg_match('/^(.*?[.])(?:\s+[A-Z]|\s*$)/u', $text, $m) === 1 ? $m[1] : $text;
    }
}
