<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ast\Support;

/**
 * Is a file FROZEN — declared intentionally immutable, its shape deliberate and impossible to refactor
 * (a shipped schema migration that must stay standalone, a generated file, a legacy leaf)? A frozen file
 * is still SCANNED (the call graph and provenance engine need it) but is never a TARGET — never flagged
 * as sinful, never rewritten by a repenter.
 *
 * Freezing is FILE-level, three interchangeable markers anywhere in the source:
 *  - a `#[Frozen]` attribute (the consumer's own attribute, matched by short name — no wiring),
 *  - an `@frozen` docblock tag,
 *  - the {@see FILE_MARKER} stamp that `commandments freeze <path>` writes.
 *
 * A judge/repent-time policy read off raw source, so it needs no AST — the shared {@see \JesseGall\CodeCommandments\Cli\Scope\Targets}
 * asks it once per file.
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
