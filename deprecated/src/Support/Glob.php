<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

/**
 * Thin wrapper over `glob()` that returns an empty list instead of `false`
 * on failure — the one home for the `glob(...) ?: []` directory-scan idiom.
 */
final class Glob
{
    /**
     * @return list<string>
     */
    public static function paths(string $pattern): array
    {
        return glob($pattern) ?: [];
    }
}
