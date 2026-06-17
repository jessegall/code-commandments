<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

use JesseGall\PhpTypes\T_String;

/**
 * A stable, content-derived identity for a single finding (sin or warning).
 *
 * The fingerprint deliberately does NOT include the line number — line
 * numbers shift when unrelated code is edited above the finding, and an
 * absolution should survive that. It DOES include the offending snippet
 * (whitespace-normalised) so that changing the flagged code itself yields
 * a different fingerprint: the old absolution stops matching and the
 * finding re-surfaces. The relative file path and prophet class scope the
 * identity so the same snippet in two files, or flagged by two prophets,
 * never share an absolution.
 *
 * An optional symbol (method/class name) disambiguates two textually
 * identical snippets within the same file when the prophet can supply one.
 */
final class Fingerprint
{
    public static function of(
        string $prophetClass,
        string $relativePath,
        ?string $symbol,
        ?string $snippet,
    ): string {
        $parts = implode(T_String::NULL_BYTE, [
            $prophetClass,
            $relativePath,
            $symbol ?? T_String::empty(),
            self::normalize($snippet ?? T_String::empty()),
        ]);

        return substr(sha1($parts), 0, 16);
    }

    /**
     * Collapse all whitespace runs to a single space and trim, so that
     * reindenting or reflowing the flagged code does not revoke an
     * absolution while a genuine edit to it does.
     */
    private static function normalize(string $snippet): string
    {
        return trim((string) preg_replace('/\s+/', ' ', $snippet));
    }
}
