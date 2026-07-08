<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Doc;

/**
 * Extracts the one-line summary from a docblock — the DESCRIPTION's first sentence, with `@tag` lines
 * dropped and `{@see Foo\Bar}` reduced to its short name. Shared by the README generator and the config /
 * hook documentation, so a builder method's or hook's own docblock is the single source of its explanation.
 */
final class Summary
{
    public static function first(string|false|null $doc): string
    {
        if ($doc === false || $doc === null) {
            return '';
        }

        // Strip the comment frame (delimiters FIRST, so a ` */` line doesn't leave a stray `/`), then the
        // line-leading asterisks, then cut from the first `@tag` line — the description is always ABOVE the
        // tags, so `@param`/`@return`/`@example` never bleed into the sentence.
        $text = str_replace(['/**', '*/'], '', $doc);
        $text = (string) preg_replace('/^\s*\*\s?/m', '', $text);
        $text = (string) preg_split('/^\s*@\w+/m', $text, 2)[0];

        // `{@see Foo\Bar::baz}` → `baz`/`Bar` (its short name).
        $text = (string) preg_replace_callback(
            '/\{@see\s+\\\\?([^}\s]+)\}/',
            static fn (array $m): string => (static fn (array $parts): string => (string) end($parts))(preg_split('/[\\\\:]+/', $m[1]) ?: [$m[1]]),
            $text,
        );

        $text = trim((string) preg_replace('/\s+/', ' ', $text));

        // The first sentence: a period ENDING a sentence — followed by whitespace then a capital or a code
        // span (`` ` ``), or the end. Skips mid-sentence abbreviations (`e.g.`) whose period is followed by
        // a lowercase word.
        return preg_match('/^(.*?[.])(?:\s+[A-Z`]|\s*$)/u', $text, $m) === 1 ? $m[1] : $text;
    }
}
