<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ast\Support;

/**
 * Is a file FROZEN — declared intentionally immutable? A frozen file is still SCANNED (the call graph and
 * provenance engine need it) but is never a TARGET: never flagged, never rewritten. Freezing is file-level
 * via any of three interchangeable markers: a `#[Frozen]` attribute, an `@frozen` docblock tag, or the
 * {@see FILE_MARKER} stamp `commandments freeze <path>` writes.
 */
final class Frozen
{
    /** The whole-file freeze stamp `commandments freeze` writes and this recognises. Stated once. */
    public const string FILE_MARKER = '@code-commandments-frozen';

    public static function isFrozen(string $source): bool
    {
        return str_contains($source, self::FILE_MARKER)
            || str_contains($source, '#[Frozen]')
            || preg_match('/@frozen\b/i', $source) === 1;
    }
}
