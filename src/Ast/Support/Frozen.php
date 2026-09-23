<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ast\Support;

use JesseGall\CodeCommandments\Language;

/**
 * Is a file FROZEN — declared intentionally immutable? It is still scanned, but never a target. Any of
 * four markers freezes it — `#[Frozen]`, an `@frozen` tag, the {@see FILE_MARKER} stamp `commandments
 * freeze` writes, or the {@see GENERATED_MARKER} a generator stamps — and only where it is DECLARED, as
 * the attribute or in a comment of the file's own language: code that merely spells one is not asking (#405).
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

    public static function isFrozen(string $source, Language $language = Language::Php): bool
    {
        if (! self::mentionsAMarker($source)) {
            return false; // Nothing to weigh. Every ordinary file takes this path, so the tokenizer
            // below runs only for the handful that say something about freezing at all.
        }

        if ($language !== Language::Php) {
            return self::commentDeclaresFreeze($source, $language);
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
     * Does a comment line of $language declare the freeze? The PHP tokenizer reads only PHP, so every
     * other language is read by its own comment delimiter.
     */
    private static function commentDeclaresFreeze(string $source, Language $language): bool
    {
        foreach (explode("\n", $source) as $line) {
            if ($language->isCommentLine($line) && self::declaresFreeze($line)) {
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
