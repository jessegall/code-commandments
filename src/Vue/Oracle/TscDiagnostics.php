<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue\Oracle;

/**
 * Reads the resolved types back out of a checker's diagnostics — the inverse of {@see TypeProbe}.
 * Each probe surfaces a `TS2322` assignability error of the shape
 *
 *   Type 'number[]' is not assignable to type '__CcNo_pageSizes'.
 *
 * whose SOURCE type (`number[]`) is the local's resolved type and whose TARGET (`__CcNo_pageSizes`)
 * names the local. Diagnostics are flat log text, not code — plain string scanning, no AST.
 */
final class TscDiagnostics
{
    private const string PIVOT = "' is not assignable to type '" . TypeProbe::MARKER;

    private const string LEAD = "Type '";

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

            // A checker that also fell to `unknown`/`any` tells us nothing the AST didn't already.
            if ($name !== null && $resolved !== '' && $resolved !== 'unknown' && $resolved !== 'any') {
                $types[$name] = $resolved;
            }
        }

        return $types;
    }

    /** The substring from $from up to the next $stop, or null when there is no closing $stop. */
    private static function until(string $line, int $from, string $stop): ?string
    {
        $end = strpos($line, $stop, $from);

        return $end === false ? null : substr($line, $from, $end - $from);
    }
}
