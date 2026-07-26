<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ast\Support;

/**
 * The SHAPE of a docblock — its delimiters, not its prose. A docblock reads as a block: the opening
 * delimiter stands on its own line, every line of content carries one star, and the closing delimiter
 * stands on its own. The single home of that form, so the rule that finds a one-liner and the fix that
 * expands it can never disagree about what "canonical" means.
 */
final class Docblock
{
    /**
     * Does this docblock share a line with its own text — the whole thing on one line, or a first/last
     * line carrying content next to a delimiter? Empty of content, it is left alone: there is nothing
     * to put on a line of its own.
     */
    public static function isInline(string $text): bool
    {
        $lines = self::lines($text);

        if (self::contentOf($text) === []) {
            return false;
        }

        return trim($lines[0]) !== '/**' || trim($lines[count($lines) - 1]) !== '*/';
    }

    /**
     * The docblock rewritten in canonical form at $indent — the original content, verbatim and in order,
     * one `*` line each, between delimiters that stand alone.
     */
    public static function canonical(string $text, string $indent): string
    {
        $body = '';

        foreach (self::contentOf($text) as $line) {
            $body .= $line === '' ? "\n{$indent} *" : "\n{$indent} * {$line}";
        }

        return "/**{$body}\n{$indent} */";
    }

    /**
     * The docblock's content lines, stripped of the delimiters and the per-line `*`, with any leading
     * and trailing blank lines dropped. A blank line INSIDE (the paragraph break before `@param`) is
     * kept as an empty entry.
     *
     * @return list<string>
     */
    private static function contentOf(string $text): array
    {
        $body = trim($text);

        // The delimiters come off FIRST — strip the per-line star before them and the opening `/**`
        // reads as content that happens to begin with slashes.
        if (str_starts_with($body, '/**')) {
            $body = substr($body, 3);
        }

        if (str_ends_with($body, '*/')) {
            $body = substr($body, 0, -2);
        }

        $lines = [];

        foreach (self::lines($body) as $line) {
            $line = trim($line);
            $lines[] = rtrim(str_starts_with($line, '*') ? ltrim(substr($line, 1)) : $line);
        }

        while ($lines !== [] && $lines[0] === '') {
            array_shift($lines);
        }

        while ($lines !== [] && $lines[count($lines) - 1] === '') {
            array_pop($lines);
        }

        return array_values($lines);
    }

    /**
     * @return list<string>
     */
    private static function lines(string $text): array
    {
        return preg_split('/\R/', $text) ?: [$text];
    }
}
