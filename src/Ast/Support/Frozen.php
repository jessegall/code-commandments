<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ast\Support;

/**
 * Is a file FROZEN — declared intentionally immutable? A frozen file is still SCANNED (the call graph and
 * provenance engine need it) but is never a TARGET: never flagged, never rewritten. Freezing is file-level
 * via any of four interchangeable markers: a `#[Frozen]` attribute, an `@frozen` docblock tag, the
 * {@see FILE_MARKER} stamp `commandments freeze <path>` writes, or the {@see GENERATED_MARKER} a generator
 * stamps on the files it owns — a generated file is immutable in the only sense that matters, since the
 * next regeneration overwrites whatever a human fixed in it.
 */
final class Frozen
{
    /** The whole-file freeze stamp `commandments freeze` writes and this recognises. Stated once. */
    public const string FILE_MARKER = '@code-commandments-frozen';

    /** The stamp a generator puts on a file it REGENERATES — hand-fixing it is work that cannot survive. */
    public const string GENERATED_MARKER = '@code-commandments-generated';

    public static function isFrozen(string $source): bool
    {
        return str_contains($source, self::FILE_MARKER)
            || str_contains($source, self::GENERATED_MARKER)
            || str_contains($source, '#[Frozen]')
            || preg_match('/@frozen\b/i', $source) === 1;
    }
}
