<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\CallGraph;

use PhpParser\Node;
use PhpParser\PrettyPrinter;
use JesseGall\PhpTypes\T_String;

/**
 * A structural fingerprint of a method body — pretty-printed with local
 * variable names canonicalised ($v1, $v2…) so a copy-pasted method still
 * matches when its variables were renamed. Both the {@see CodebaseIndex}
 * (which counts occurrences) and the duplicate-code prophet (which decides
 * what to flag) call THIS so their notion of "the same body" can't drift.
 */
final class MethodBodyHash
{
    /**
     * The body's fingerprint + printed line count, or null when the body is
     * empty or smaller than $minLines (too short to be worth de-duplicating).
     *
     * @return array{hash: string, lines: int}|null
     */
    public static function of(Node\Stmt\ClassMethod $method, int $minLines): ?array
    {
        if ($method->stmts === null || $method->stmts === []) {
            return null;
        }

        $code = trim((new PrettyPrinter\Standard)->prettyPrint($method->stmts));

        if (T_String::isEmpty($code)) {
            return null;
        }

        $lines = substr_count($code, T_String::NEWLINE) + 1;

        if ($lines < $minLines) {
            return null;
        }

        return ['hash' => md5(self::canonicalise($code)), 'lines' => $lines];
    }

    /**
     * Fingerprints of each LEADING statement-run prefix of the body
     * (`statements[0..k]` for `k` below the full count) whose printed length is
     * at least $minLines. This lets the duplicate detector catch a shared
     * PREAMBLE between two methods that then diverge — not only whole-body
     * matches. The full body is excluded here (that is {@see self::of()}).
     *
     * @return list<array{hash: string, lines: int}>
     */
    public static function leadingFragments(Node\Stmt\ClassMethod $method, int $minLines): array
    {
        $stmts = $method->stmts;

        if ($stmts === null || count($stmts) < 2) {
            return [];
        }

        $printer = new PrettyPrinter\Standard;
        $fragments = [];
        $count = count($stmts);

        for ($k = 1; $k < $count; $k++) {
            $code = trim($printer->prettyPrint(array_slice($stmts, 0, $k)));

            if (T_String::isEmpty($code)) {
                continue;
            }

            $lines = substr_count($code, T_String::NEWLINE) + 1;

            if ($lines < $minLines) {
                continue;
            }

            $fragments[] = ['hash' => md5(self::canonicalise($code)), 'lines' => $lines];
        }

        return $fragments;
    }

    /**
     * Rename local variables to positional placeholders by first occurrence,
     * leaving `$this` alone — so two bodies that differ only in variable names
     * fingerprint identically.
     */
    private static function canonicalise(string $code): string
    {
        $map = [];
        $next = 0;

        return (string) preg_replace_callback(
            '/\$(?!this\b)[a-zA-Z_]\w*/',
            static function (array $m) use (&$map, &$next): string {
                return $map[$m[0]] ??= '$v' . (++$next);
            },
            $code,
        );
    }
}
