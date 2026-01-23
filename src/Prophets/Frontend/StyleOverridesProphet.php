<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Frontend;

use JesseGall\CodeCommandments\Commandments\FrontendCommandment;
use JesseGall\CodeCommandments\Results\Judgment;

/**
 * Avoid style/class overrides on base components - use semantic props instead.
 *
 * Don't override styles on base UI components using class attributes.
 * Instead, the component should expose semantic props for variants.
 *
 * Layout/spacing classes (margin, grid, flex positioning) are allowed
 * as they are contextual concerns of the parent, not the component.
 */
class StyleOverridesProphet extends FrontendCommandment
{
    /**
     * Base components that shouldn't have class overrides.
     */
    protected array $baseComponents = ['ItemCard', 'Card', 'Button', 'Input', 'Badge'];

    /**
     * Patterns for allowed layout/spacing classes.
     * These are contextual concerns that belong to the parent layout.
     */
    protected array $allowedPatterns = [
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

    public function applicableExtensions(): array
    {
        return ['vue'];
    }

    public function description(): string
    {
        return 'Avoid style/class overrides on base components - use semantic props instead';
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
Don't override styles on base UI components using class attributes.
Instead, the component should expose semantic props for variants.

Bad:
    <ItemCard class="bg-red-100 border-red-500">
    <Button class="w-full">
    <Button class="text-sm">

Good:
    <ItemCard variant="danger">
    <Button fullWidth>
    <Button size="sm">

Layout/spacing classes ARE allowed (they're parent context concerns):
    <Button class="mt-4 col-span-2">     <!-- OK: margin and grid layout -->
    <Card class="absolute top-0 right-0"> <!-- OK: positioning -->
    <Input class="flex-1">                <!-- OK: flex behavior -->

If the component doesn't support your use case, consider:
1. Adding a semantic prop to the component
2. Creating a specialized variant component
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        if ($this->shouldSkipExtension($filePath)) {
            return $this->righteous();
        }

        $template = $this->extractTemplate($content);

        if ($template === null) {
            return $this->skip('No template section found');
        }

        $templateContent = $template['content'];
        $templateStart = $template['start'];
        $sins = [];

        foreach ($this->baseComponents as $component) {
            // Skip the component definition itself
            if (str_contains($filePath, "/{$component}.vue") || str_contains($filePath, '/'.strtolower($component).'/')) {
                continue;
            }

            // Look for base components with class or content-class attributes
            // Capture the full attribute value
            $pattern = '/<'.preg_quote($component, '/').'[^>]*((?:content-)?class)=["\']([^"\']*)["\'][^>]*>/i';

            preg_match_all($pattern, $templateContent, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

            foreach ($matches as $match) {
                $offset = $match[0][1];
                $classValue = $match[2][0];

                // Extract individual classes and filter out allowed layout classes
                $classes = preg_split('/\s+/', trim($classValue), -1, PREG_SPLIT_NO_EMPTY);
                $disallowedClasses = $this->filterDisallowedClasses($classes);

                // Only flag if there are disallowed classes
                if (empty($disallowedClasses)) {
                    continue;
                }

                $line = $this->getLineFromOffset($content, $templateStart + $offset);
                $disallowedList = implode(', ', $disallowedClasses);

                $sins[] = $this->sinAt(
                    $line,
                    "Style override on <{$component}>: {$disallowedList}",
                    $this->getSnippet($templateContent, $offset, 60),
                    "Use semantic props instead (e.g., 'variant', 'size', 'fullWidth')"
                );
            }
        }

        return empty($sins) ? $this->righteous() : $this->fallen($sins);
    }

    /**
     * Filter out allowed layout/spacing classes, returning only disallowed ones.
     *
     * @param  array<string>  $classes
     * @return array<string>
     */
    protected function filterDisallowedClasses(array $classes): array
    {
        return array_filter($classes, function (string $class): bool {
            foreach ($this->allowedPatterns as $pattern) {
                if (preg_match($pattern, $class)) {
                    return false; // Class is allowed
                }
            }

            return true; // Class is not allowed
        });
    }
}
