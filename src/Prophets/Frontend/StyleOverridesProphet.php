<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Frontend;

use JesseGall\CodeCommandments\Commandments\FrontendCommandment;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Support\Pipes\ProphetPipeline;
use JesseGall\CodeCommandments\Support\Pipeline;
use JesseGall\CodeCommandments\Support\RegexMatcher;
use JesseGall\CodeCommandments\Support\TailwindClassFilter;

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
     *
     * @var array<string>
     */
    protected array $baseComponents = ['ItemCard', 'Card', 'Button', 'Input', 'Badge'];

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

        $pipeline = ProphetPipeline::make($filePath, $content)
            ->extractTemplate();

        if ($pipeline->shouldSkip()) {
            return $pipeline->judge();
        }

        // Check each base component for violations (skip if file is the component itself)
        $components = Pipeline::from($this->baseComponents)
            ->reject(fn ($component) => $this->isComponentDefinition($filePath, $component))
            ->toArray();

        foreach ($components as $component) {
            $this->findViolationsFor($pipeline, $component);
        }

        return $pipeline->judge();
    }

    /**
     * Check if the file is the component definition itself.
     */
    private function isComponentDefinition(string $filePath, string $component): bool
    {
        return str_contains($filePath, "/{$component}.vue")
            || str_contains($filePath, '/'.strtolower($component).'/');
    }

    /**
     * Find and add violations for a specific component.
     */
    private function findViolationsFor(ProphetPipeline $pipeline, string $component): void
    {
        // Pattern for static class attributes (not :class bindings)
        $pattern = '/<'.preg_quote($component, '/').'[^>]*\s(?!:)((?:content-)?class)=["\']([^"\']*)["\'][^>]*>/i';

        $matcher = RegexMatcher::for($pipeline->getSectionContent());

        $sins = Pipeline::from($matcher->matchAll($pattern))
            ->map(function (array $match) use ($pipeline, $component, $matcher) {
                $classValue = $match['groups'][2] ?? '';
                $disallowed = TailwindClassFilter::onlyAppearance(
                    TailwindClassFilter::parse($classValue)
                );

                if (empty($disallowed)) {
                    return null;
                }

                return $this->sinAt(
                    $pipeline->getLineFromOffset($match['offset']),
                    "Style override on <{$component}>: ".implode(', ', $disallowed),
                    $matcher->getSnippet($match['offset'], 60),
                    "Use semantic props instead (e.g., 'variant', 'size', 'fullWidth')"
                );
            })
            ->compact()
            ->toArray();

        $pipeline->addSins($sins);
    }
}
