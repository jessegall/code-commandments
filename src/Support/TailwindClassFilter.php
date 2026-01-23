<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

/**
 * Filters Tailwind CSS classes into layout (allowed) and appearance (disallowed) categories.
 *
 * Layout classes are contextual concerns that belong to the parent layout,
 * while appearance classes should use semantic component props.
 */
final class TailwindClassFilter
{
    /**
     * Patterns for layout/spacing classes that are always allowed.
     * These are contextual concerns that belong to the parent layout.
     *
     * @var array<string>
     */
    private static array $layoutPatterns = [
        // Spacing/Margin (including negative)
        '/^-?m[trblxyse]?-/',
        '/^-?p[trblxyse]?-/',
        '/^space-[xy]-/',
        '/^gap-/',

        // Grid layout
        '/^col-span-/',
        '/^col-start-/',
        '/^col-end-/',
        '/^row-span-/',
        '/^row-start-/',
        '/^row-end-/',

        // Flexbox behavior
        '/^flex-(1|auto|initial|none)$/',
        '/^(grow|shrink)(-0)?$/',
        '/^self-/',
        '/^justify-self-/',
        '/^place-self-/',
        '/^order-/',

        // Positioning
        '/^(absolute|relative|fixed|sticky)$/',
        '/^-?(top|right|bottom|left|inset)-/',
        '/^z-/',

        // Arbitrary width/height constraints
        '/^(min-|max-)?(w|h)-\[/',

        // Display/Visibility
        '/^(hidden|block|inline|inline-block|inline-flex|invisible|visible)$/',
    ];

    /**
     * Parse a class attribute value into individual classes.
     *
     * @return array<string>
     */
    public static function parse(string $classValue): array
    {
        return Pipeline::from($classValue)
            ->pipe(fn ($v) => preg_split('/\s+/', trim($v), -1, PREG_SPLIT_NO_EMPTY))
            ->toArray();
    }

    /**
     * Check if a class is a layout class (allowed on components).
     *
     * @param  array<string>  $additionalPatterns  Additional patterns to consider as layout classes
     */
    public static function isLayoutClass(string $class, array $additionalPatterns = []): bool
    {
        $patterns = empty($additionalPatterns)
            ? self::$layoutPatterns
            : array_merge(self::$layoutPatterns, $additionalPatterns);

        return Str::matchesAny($class, $patterns);
    }

    /**
     * Check if a class is an appearance class (should use semantic props).
     *
     * @param  array<string>  $additionalLayoutPatterns  Additional patterns to consider as layout classes
     */
    public static function isAppearanceClass(string $class, array $additionalLayoutPatterns = []): bool
    {
        return ! self::isLayoutClass($class, $additionalLayoutPatterns);
    }

    /**
     * Filter classes to only layout classes.
     *
     * @param  array<string>  $classes
     * @param  array<string>  $additionalPatterns  Additional patterns to consider as layout classes
     * @return array<string>
     */
    public static function onlyLayout(array $classes, array $additionalPatterns = []): array
    {
        return Pipeline::from($classes)
            ->filter(fn ($class) => self::isLayoutClass($class, $additionalPatterns))
            ->values()
            ->toArray();
    }

    /**
     * Filter classes to only appearance classes (disallowed).
     *
     * @param  array<string>  $classes
     * @param  array<string>  $additionalLayoutPatterns  Additional patterns to consider as layout classes
     * @return array<string>
     */
    public static function onlyAppearance(array $classes, array $additionalLayoutPatterns = []): array
    {
        return Pipeline::from($classes)
            ->filter(fn ($class) => self::isAppearanceClass($class, $additionalLayoutPatterns))
            ->values()
            ->toArray();
    }

    /**
     * Check if all classes are layout classes.
     *
     * @param  array<string>  $classes
     * @param  array<string>  $additionalPatterns  Additional patterns to consider as layout classes
     */
    public static function allLayout(array $classes, array $additionalPatterns = []): bool
    {
        return Pipeline::from($classes)
            ->filter(fn ($class) => self::isAppearanceClass($class, $additionalPatterns))
            ->isEmpty();
    }

    /**
     * Check if any class is an appearance class.
     *
     * @param  array<string>  $classes
     * @param  array<string>  $additionalLayoutPatterns  Additional patterns to consider as layout classes
     */
    public static function hasAppearanceClasses(array $classes, array $additionalLayoutPatterns = []): bool
    {
        return Pipeline::from($classes)
            ->filter(fn ($class) => self::isAppearanceClass($class, $additionalLayoutPatterns))
            ->isNotEmpty();
    }

    /**
     * Get the layout patterns (useful for extending).
     *
     * @return array<string>
     */
    public static function getLayoutPatterns(): array
    {
        return self::$layoutPatterns;
    }
}
