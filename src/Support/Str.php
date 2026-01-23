<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

/**
 * String helper utilities.
 */
final class Str
{
    /**
     * Convert camelCase to kebab-case.
     */
    public static function toKebabCase(string $value): string
    {
        return strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $value) ?? $value);
    }

    /**
     * Convert kebab-case to camelCase.
     */
    public static function toCamelCase(string $value): string
    {
        return lcfirst(str_replace(' ', '', ucwords(str_replace('-', ' ', $value))));
    }

    /**
     * Convert to PascalCase.
     */
    public static function toPascalCase(string $value): string
    {
        return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $value)));
    }

    /**
     * Check if string contains any of the given needles.
     *
     * @param  array<string>  $needles
     */
    public static function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if string matches any of the given patterns.
     *
     * @param  array<string>  $patterns  Regex patterns
     */
    public static function matchesAny(string $value, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $value)) {
                return true;
            }
        }

        return false;
    }
}
