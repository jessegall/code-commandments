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
 *
 * A marker counts only where it is DECLARED — in a comment, or as the attribute itself. Code that merely
 * SPELLS one, like a help text explaining the feature or a test asserting on the stamp, is talking about
 * freezing rather than asking for it, and treating that as a freeze hides every finding in the file
 * without saying so (#405). The language's own tokenizer draws the line.
 */
final class Frozen
{
    /**
     * The whole-file freeze stamp `commandments freeze` writes and this recognises. Stated once.
     */
    public const string FILE_MARKER = '@code-commandments-frozen';

    /**
     * The stamp a generator puts on a file it REGENERATES — hand-fixing it is work that cannot survive.
     */
    public const string GENERATED_MARKER = '@code-commandments-generated';

    /**
     * The attribute form of the same declaration — `#[Frozen]`.
     */
    private const string ATTRIBUTE = 'Frozen';

    public static function isFrozen(string $source): bool
    {
        if (! self::mentionsAMarker($source)) {
            return false; // Nothing to weigh. Every ordinary file takes this path, so the tokenizer
            // below runs only for the handful that say something about freezing at all.
        }

        $tokens = token_get_all($source);

        foreach ($tokens as $index => $token) {
            if (! is_array($token)) {
                continue;
            }

            if (in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true) && self::declaresFreeze($token[1])) {
                return true;
            }

            if ($token[0] === T_ATTRIBUTE && self::namesTheAttribute($tokens, $index)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Is this comment a freeze DECLARATION — one of the stamps, or an `@frozen` tag?
     */
    private static function declaresFreeze(string $comment): bool
    {
        return str_contains($comment, self::FILE_MARKER)
            || str_contains($comment, self::GENERATED_MARKER)
            || preg_match('/@frozen\b/i', $comment) === 1;
    }

    /**
     * Does the attribute opening at $index name {@see ATTRIBUTE} — `#[Frozen]`, whatever the spacing?
     *
     * @param  array<int, array{0: int, 1: string, 2: int}|string>  $tokens
     */
    private static function namesTheAttribute(array $tokens, int $index): bool
    {
        $count = count($tokens);

        for ($next = $index + 1; $next < $count; $next++) {
            $token = $tokens[$next];

            if (is_array($token) && $token[0] === T_WHITESPACE) {
                continue;
            }

            return is_array($token) && $token[0] === T_STRING && $token[1] === self::ATTRIBUTE;
        }

        return false;
    }

    /**
     * Does the source spell any marker at all? A plain byte test, and the only one an unfrozen file runs.
     */
    private static function mentionsAMarker(string $source): bool
    {
        return str_contains($source, self::FILE_MARKER)
            || str_contains($source, self::GENERATED_MARKER)
            || str_contains($source, self::ATTRIBUTE)
            || preg_match('/@frozen\b/i', $source) === 1;
    }
}
