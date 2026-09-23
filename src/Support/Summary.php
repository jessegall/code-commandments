<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

use ReflectionClass;

/**
 * The one line a class says about itself — the first sentence of its docblock's description, `@tag`
 * lines dropped and `{@see …}` tags reduced to their short name. The README tables, the journal plugin's
 * settings and each hook's own help page all read it.
 */
final class Summary
{
    public static function of(object|string $subject): string
    {
        $doc = (new ReflectionClass($subject))->getDocComment();

        if ($doc === false) {
            return '';
        }

        // The frame first (so a ` */` line leaves no stray `/`), then the leading asterisks, then
        // everything from the first `@tag` line — the description always sits above the tags.
        $text = (string) preg_replace('#/\*\*|\*/#', '', $doc);
        $text = (string) preg_replace('/^\s*\*\s?/m', '', $text);
        $text = (string) preg_split('/^\s*@\w+/m', $text, 2)[0];
        $text = (string) preg_replace_callback(
            '/\{@see\s+\\\\?([^}\s]+)\}/',
            static fn (array $m): string => (static fn (array $parts): string => (string) end($parts))(preg_split('/[\\\\:]+/', $m[1]) ?: [$m[1]]),
            $text,
        );
        $text = trim((string) preg_replace('/\s+/', ' ', $text));

        // A period that ENDS a sentence — followed by whitespace then a capital or a code span, or the
        // end of the text — so mid-sentence abbreviations (`e.g.`, `i.e.`) never cut it short.
        return preg_match('/^(.*?[.])(?:\s+[A-Z`]|\s*$)/u', $text, $m) === 1 ? $m[1] : $text;
    }
}
