<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Pipes;

use Closure;
use JesseGall\CodeCommandments\Support\Pipes\MatchResult;
use JesseGall\CodeCommandments\Support\Str;
use JesseGall\CodeCommandments\Support\TailwindClassFilter;

/**
 * Common predicates for filtering in pipelines.
 *
 * Usage:
 *   ->filter(Predicates::matchesPattern('/pattern/'))
 *   ->reject(Predicates::containsAny(['foo', 'bar']))
 *   ->filter(Predicates::isLayoutClass())
 */
final class Predicates
{
    /**
     * Check if a value matches a pattern.
     */
    public static function matchesPattern(string $pattern): Closure
    {
        return fn ($value) => (bool) preg_match($pattern, (string) $value);
    }

    /**
     * Check if a value matches any of the patterns.
     *
     * @param  array<string>  $patterns
     */
    public static function matchesAnyPattern(array $patterns): Closure
    {
        return fn ($value) => Str::matchesAny((string) $value, $patterns);
    }

    /**
     * Check if a value contains a substring.
     */
    public static function contains(string $needle): Closure
    {
        return fn ($value) => str_contains((string) $value, $needle);
    }

    /**
     * Check if a value contains any of the substrings.
     *
     * @param  array<string>  $needles
     */
    public static function containsAny(array $needles): Closure
    {
        return fn ($value) => Str::containsAny((string) $value, $needles);
    }

    /**
     * Check if a value starts with a prefix.
     */
    public static function startsWith(string $prefix): Closure
    {
        return fn ($value) => str_starts_with((string) $value, $prefix);
    }

    /**
     * Check if a value ends with a suffix.
     */
    public static function endsWith(string $suffix): Closure
    {
        return fn ($value) => str_ends_with((string) $value, $suffix);
    }

    /**
     * Check if a value equals another value.
     */
    public static function equals(mixed $expected): Closure
    {
        return fn ($value) => $value === $expected;
    }

    /**
     * Check if a value is empty.
     */
    public static function isEmpty(): Closure
    {
        return fn ($value) => empty($value);
    }

    /**
     * Check if a value is not empty.
     */
    public static function isNotEmpty(): Closure
    {
        return fn ($value) => ! empty($value);
    }

    /**
     * Check if a Tailwind class is a layout class.
     */
    public static function isLayoutClass(): Closure
    {
        return fn ($class) => TailwindClassFilter::isLayoutClass((string) $class);
    }

    /**
     * Check if a Tailwind class is an appearance class.
     */
    public static function isAppearanceClass(): Closure
    {
        return fn ($class) => TailwindClassFilter::isAppearanceClass((string) $class);
    }

    /**
     * Check if a file path is for a specific component.
     */
    public static function isComponentFile(string $component): Closure
    {
        return fn ($filePath) => str_contains((string) $filePath, "/{$component}.vue")
            || str_contains((string) $filePath, '/'.strtolower($component).'/');
    }

    /**
     * Check if a match has a specific group.
     */
    public static function hasGroup(int|string $group): Closure
    {
        return function ($match) use ($group) {
            $groups = $match instanceof MatchResult ? $match->groups : ($match['groups'] ?? []);

            return isset($groups[$group]) && $groups[$group] !== '';
        };
    }

    /**
     * Get a group value from a match.
     */
    public static function getGroup(int|string $group, mixed $default = null): Closure
    {
        return function ($match) use ($group, $default) {
            $groups = $match instanceof MatchResult ? $match->groups : ($match['groups'] ?? []);

            return $groups[$group] ?? $default;
        };
    }

    /**
     * Compose multiple predicates with AND logic.
     *
     * @param  array<Closure>  $predicates
     */
    public static function all(array $predicates): Closure
    {
        return function ($value) use ($predicates) {
            foreach ($predicates as $predicate) {
                if (! $predicate($value)) {
                    return false;
                }
            }

            return true;
        };
    }

    /**
     * Compose multiple predicates with OR logic.
     *
     * @param  array<Closure>  $predicates
     */
    public static function any(array $predicates): Closure
    {
        return function ($value) use ($predicates) {
            foreach ($predicates as $predicate) {
                if ($predicate($value)) {
                    return true;
                }
            }

            return false;
        };
    }

    /**
     * Negate a predicate.
     */
    public static function not(Closure $predicate): Closure
    {
        return fn ($value) => ! $predicate($value);
    }

    /**
     * Always return true.
     */
    public static function always(): Closure
    {
        return fn () => true;
    }

    /**
     * Always return false.
     */
    public static function never(): Closure
    {
        return fn () => false;
    }
}
