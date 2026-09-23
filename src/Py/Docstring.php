<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Py;

use JesseGall\CodeCommandments\Support\Prose;

/**
 * What a docstring's reStructuredText says, read as text.
 */
final class Docstring
{
    /**
     * The headings the Google and NumPy docstring styles open a section with.
     */
    private const string SECTION = '/^(?:Args|Arguments|Parameters|Params|Other Parameters|Keyword Args|Returns?|Yields?|Raises|Attributes|Methods|Examples?|Notes?|See Also|Warnings?|Todo|References):?$/';

    /**
     * $text without its Sphinx version notes — each `.. versionadded::`, `.. versionchanged::` or
     * `.. deprecated::` directive and the lines indented under it. Those record a public API's history
     * for its users on purpose; the rest of the docstring describes the code.
     */
    public static function withoutVersionNotes(string $text): string
    {
        return (string) preg_replace('/^([ \t]*)\.\. (?:versionadded|versionchanged|deprecated)::.*(?:\n(?:\1[ \t]+.*|[ \t]*))*$/m', '', $text);
    }

    /**
     * How many paragraphs of prose $text holds before its first section — a Google `Args:` or a NumPy
     * `Parameters` over dashes — with its version notes, markup lines and indented blocks set aside.
     */
    public static function proseParagraphs(string $text): int
    {
        $lines = self::beforeFirstSection(explode("\n", self::withoutVersionNotes($text)));
        $base = self::baseIndent($lines);

        return Prose::paragraphs($lines, static fn (string $line): bool => ! self::isMarkup(ltrim($line)) && strlen($line) - strlen(ltrim($line)) <= $base);
    }

    /**
     * Is $line markup rather than prose — a doctest `>>>`, a reST field (`:param app:`) or a directive (`..`)?
     */
    private static function isMarkup(string $line): bool
    {
        return str_starts_with($line, '>>>') || str_starts_with($line, '..') || preg_match('/^:[\w ]+:/', $line) === 1;
    }

    /**
     * $lines up to the one that opens their first section — all of them when there is none.
     *
     * @param  list<string>  $lines
     * @return list<string>
     */
    private static function beforeFirstSection(array $lines): array
    {
        foreach ($lines as $at => $line) {
            if (preg_match(self::SECTION, trim($line)) !== 1) {
                continue;
            }

            $underlined = array_key_exists($at + 1, $lines) && preg_match('/^-{3,}$/', trim($lines[$at + 1])) === 1;

            if (str_ends_with(trim($line), ':') || $underlined) {
                return array_slice($lines, 0, $at);
            }
        }

        return $lines;
    }

    /**
     * The indentation the body of $lines is written at — the first line sits right after the quotes.
     *
     * @param  list<string>  $lines
     */
    private static function baseIndent(array $lines): int
    {
        $indents = array_map(static fn (string $line): int => strlen($line) - strlen(ltrim($line)), array_filter(array_slice($lines, 1), static fn (string $line): bool => trim($line) !== ''));

        return $indents === [] ? 0 : min($indents);
    }
}
