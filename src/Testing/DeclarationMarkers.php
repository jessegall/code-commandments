<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Testing;

use JesseGall\CodeCommandments\Vue\Codebase;

/**
 * Declaration-space markers — the frontend analog of the template's `<!-- @sin -->`
 * comments and the backend's `#[Sinful]` attributes, for sins that live on a TYPE
 * rather than an element. A `// @sin Name` (or `@righteous Name`) comment on the
 * line(s) immediately above an `interface`/`type` marks that declaration, the way a
 * template comment marks the element that follows it.
 *
 * A declaration's location comes from the parsed {@see \JesseGall\CodeCommandments\Vue\TypeDeclaration}
 * (so it is the exact `file:line` a finding reports); the marker comment is read from
 * the raw source above it — comment text, not structure, so no parser is needed for it.
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
    private static function markersAbove(array $lines, int $at, string $tag): array
    {
        $names = [];

        for ($n = $at - 1; $n >= 1; $n--) {
            $text = trim($lines[$n - 1] ?? '');

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
