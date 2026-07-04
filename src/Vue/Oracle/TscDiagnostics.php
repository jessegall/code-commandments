<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue\Oracle;

/**
 * Extracts resolved types from checker diagnostics — the inverse of {@see TypeProbe}. Parses
 * `TS2322` assignability errors to map local names to their resolved types via string scanning.
 */
final class TscDiagnostics
{
    private const string PIVOT = "' is not assignable to type '" . TypeProbe::MARKER;

    private const string LEAD = "Type '";

    /**
     * A resolved type longer than this is a monster — a composable's full structural type spelt out
     * inline — which reads worse as a prop annotation than a plain `unknown`, so we drop it. (It also
     * bounds what a truncated/degenerate type could cost downstream.)
     */
    private const int MAX_TYPE_LENGTH = 200;

    /**
     * @return array<string, string>  local name => resolved type
     */
    public static function types(string $output): array
    {
        $types = [];

        foreach (explode("\n", $output) as $line) {
            $pivot = strpos($line, self::PIVOT);
            $lead = strpos($line, self::LEAD);

            if ($pivot === false || $lead === false || $lead >= $pivot) {
                continue;
            }

            $resolved = substr($line, $lead + strlen(self::LEAD), $pivot - $lead - strlen(self::LEAD));
            $name = self::until($line, $pivot + strlen(self::PIVOT), "'");

            if ($name !== null && self::usable($resolved)) {
                $types[$name] = $resolved;
            }
        }

        return $types;
    }

    /**
     * Is a resolved type worth taking? Not the empty string, not `unknown`/`any` (which tell us
     * nothing the AST didn't already), and not a monster too long to read as an annotation.
     */
    private static function usable(string $type): bool
    {
        return $type !== ''
            && $type !== 'unknown'
            && $type !== 'any'
            && strlen($type) <= self::MAX_TYPE_LENGTH;
    }

    /** The substring from $from up to the next $stop, or null when there is no closing $stop. */
    private static function until(string $line, int $from, string $stop): ?string
    {
        $end = strpos($line, $stop, $from);

        return $end === false ? null : substr($line, $from, $end - $from);
    }
}
