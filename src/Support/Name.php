<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

/**
 * The two spellings one commandment wears, and the conversion between them: a class name in
 * StudlyCase (`NullableElementReturn`) and the id typed on the command line (`nullable-element-return`).
 * Both live here so a scaffolded sin, its detector class, its `--sin=` id and its skill slug all
 * derive from ONE input and can never disagree.
 */
final class Name
{
    /**
     * A name in StudlyCase, from any spelling — `nullable-element-return`, `nullable_element_return`
     * and `Nullable Element Return` all give `NullableElementReturn`. An already-studly name is
     * returned unchanged, so this is safe to apply twice.
     */
    public static function studly(string $name): string
    {
        $words = preg_split('/[^A-Za-z0-9]+/', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return implode('', array_map(static fn (string $word): string => ucfirst($word), $words));
    }

    /**
     * The spelling-insensitive KEY of an identifier — lowercased with `_` and `-` dropped, so the
     * snake, camel, Pascal and kebab spellings of one name collapse to a single key. What two sides
     * of a boundary compare by when each names the same field in its own language's spelling.
     */
    public static function canonical(string $identifier): string
    {
        return str_replace(['_', '-'], '', strtolower($identifier));
    }

    /**
     * A name in kebab-case — `NullableElementReturn` → `nullable-element-return`. Every camelCase
     * hump becomes a boundary, and an acronym run splits before its final capital
     * (`ParseHTMLDocument` → `parse-html-document`), which is how a reader says it aloud.
     */
    public static function kebab(string $name): string
    {
        $spaced = preg_replace(['/([a-z0-9])([A-Z])/', '/([A-Z]+)([A-Z][a-z])/'], '$1-$2', $name) ?? $name;

        return strtolower((string) preg_replace('/[^A-Za-z0-9]+/', '-', trim($spaced, '-')));
    }
}
