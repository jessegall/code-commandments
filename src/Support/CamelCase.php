<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

/**
 * camelCase identifier helpers. The one home for reading the token structure of a property/method name, so
 * an analysis groups `broadcastUrl` and `broadcastEnabled` by their shared leading token without each
 * re-deriving the split. Detects an upper-case boundary via `strtoupper`/`strtolower` (not `ctype_*` /
 * `preg_*`), so it is safe to compose from any layer.
 */
final class CamelCase
{
    /**
     * The leading lower-case token of a camelCase name — the run of characters up to (not including) the
     * first upper-case letter, lower-cased. `broadcastUrl` → `broadcast`, `wireType` → `wire`, `name` →
     * `name`, `URL` → `` (starts upper — no lower-case lead).
     */
    public static function leadingToken(string $name): string
    {
        $token = '';

        foreach (str_split($name) as $char) {
            if (self::isUpper($char)) {
                break;
            }

            $token .= $char;
        }

        return $token;
    }

    /**
     * The remainder after {@see leadingToken} — what a shared prefix leaves behind on each member
     * (`broadcastUrl` with token `broadcast` → `Url`). Empty when the name IS just its leading token.
     */
    public static function afterLeadingToken(string $name): string
    {
        return substr($name, strlen(self::leadingToken($name)));
    }

    private static function isUpper(string $char): bool
    {
        return $char !== strtolower($char) && $char === strtoupper($char);
    }
}
