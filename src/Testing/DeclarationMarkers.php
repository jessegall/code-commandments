<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Testing;

use JesseGall\CodeCommandments\Vue\Codebase;

/**
 * Frontend analog of `<!-- @sin -->` comments and `#[Sinful]` attributes. A `// @sin Name` comment
 * immediately above an `interface`/`type` marks that declaration from raw source (text-read, no parser).
 * Location comes from parsed {@see TypeDeclaration}, giving the exact `file:line` a finding reports.
 */
final class DeclarationMarkers
{
    /**
     * The `file:line` of every declaration marked `@{$tag} Name`, grouped by Name.
     *
     * @return array<string, list<string>>
     */
    public static function in(Codebase $codebase, string $tag): array
    {
        $marked = [];
        $lines = [];

        foreach ($codebase->typeDeclarations() as $declaration) {
            $lines[$declaration->file] ??= self::lines($declaration->file);

            foreach (self::markersAbove($lines[$declaration->file], $declaration->line, $tag) as $name) {
                $marked[$name][] = $declaration->file . ':' . $declaration->line;
            }
        }

        return $marked;
    }

    /**
     * The names in a run of `@{$tag} Name` comments immediately above line $at (1-based)
     * — walking up over consecutive comment lines, stopping at the first line that is
     * neither blank nor a comment, exactly as a template marker binds to the next element.
     *
     * @param  list<string>  $lines
     * @return list<string>
     */
    public static function markersAbove(array $lines, int $at, string $tag): array
    {
        $names = [];

        // Start from the last line that EXISTS: a declaration reported past the end of the slice
        // has nothing above it to read, rather than a run of empty strings to skip.
        for ($n = min($at - 1, count($lines)); $n >= 1; $n--) {
            $text = trim($lines[$n - 1]);

            if ($text === '') {
                continue;
            }

            if (preg_match('/@' . preg_quote($tag, '/') . '\s+(\w+)/', $text, $match) !== 1) {
                break;
            }

            $names[] = $match[1];
        }

        return $names;
    }

    /**
     * @return list<string>
     */
    private static function lines(string $file): array
    {
        return explode("\n", (string) @file_get_contents($file));
    }
}
