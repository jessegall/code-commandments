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

    /**
     * Does $text say nothing but what the signature already says — no summary, and every entry a bare
     * restatement of a parameter in $annotated or, when $returnAnnotated, of the return? `Args:` entries with
     * no description, a bare `Returns:` type, NumPy `name : type` lines, Sphinx `:param x:`, `:type x:` and
     * `:rtype:` fields. A description, a type for an unannotated parameter, or any other section earns its keep.
     *
     * @param  list<string>  $annotated
     */
    public static function onlyRestates(string $text, array $annotated, bool $returnAnnotated): bool
    {
        $lines = array_values(array_filter(array_map(trim(...), explode("\n", $text)), static fn (string $line): bool => $line !== ''));
        $section = '';
        $restated = 0;

        foreach ($lines as $at => $line) {
            $underlined = array_key_exists($at + 1, $lines) && preg_match('/^-{3,}$/', $lines[$at + 1]) === 1;

            if (preg_match('/^-{3,}$/', $line) === 1) {
                continue;
            }

            if (preg_match('/^(?:Args|Arguments|Parameters|Params):$/', $line) === 1 || ($underlined && in_array($line, ['Parameters', 'Params'], true))) {
                $section = 'args';

                continue;
            }

            if (preg_match('/^Returns?:$/', $line) === 1 || ($underlined && $line === 'Returns')) {
                $section = 'returns';

                continue;
            }

            if (! self::restates($line, $section, $annotated, $returnAnnotated)) {
                return false;
            }

            $restated++;
        }

        return $restated > 0;
    }

    /**
     * Is $line, read in $section, a bare restatement of an annotated parameter or return?
     *
     * @param  list<string>  $annotated
     */
    private static function restates(string $line, string $section, array $annotated, bool $returnAnnotated): bool
    {
        if (preg_match('/^:(?:param(?:\s+\S+)?\s+(\w+)|type\s+(\w+)):\s*(.*)$/', $line, $field) === 1) {
            $isParam = $field[1] !== '';

            return in_array($isParam ? $field[1] : $field[2], $annotated, true) && (! $isParam || $field[3] === '');
        }

        if (preg_match('/^:(?:rtype:\s*\S.*|returns?:\s*)$/', $line) === 1) {
            return $returnAnnotated;
        }

        if ($section === 'returns') {
            return $returnAnnotated && preg_match('/^[\w.\[\], |]+:?$/', $line) === 1;
        }

        return $section === 'args'
            && preg_match('/^(\w+)(?:\s*\([^)]*\))?:$|^(\w+) : \S/', $line, $entry) === 1
            && in_array($entry[1] !== '' ? $entry[1] : $entry[2], $annotated, true);
    }

    /**
     * The dotted names $text's Sphinx cross-references point at — `shop.cart.Cart` from
     * ``:class:`~shop.cart.Cart` `` or ``:func:`total <shop.cart.total>` `` — leaving out a bare name, which
     * resolves against wherever Sphinx is told to look.
     *
     * @return list<string>
     */
    public static function references(string $text): array
    {
        preg_match_all('/:(?:py:)?(?:class|func|meth|attr|mod|obj|exc|data|const):`(?:[^`<]*<)?[~!.]?([\w.]+)>?`/', $text, $found);

        return array_values(array_unique(array_filter($found[1], static fn (string $name): bool => str_contains($name, '.'))));
    }
}
