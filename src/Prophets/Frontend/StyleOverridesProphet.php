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
 */
class StyleOverridesProphet extends FrontendCommandment
{
    /**
     * Base components that shouldn't have class overrides.
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

Good:
    <ItemCard variant="danger">
    <Button fullWidth>

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
            $pattern = '/<'.preg_quote($component, '/').'[^>]*(class=|content-class=)/i';

            preg_match_all($pattern, $templateContent, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

            foreach ($matches as $match) {
                $offset = $match[0][1];
                $line = $this->getLineFromOffset($content, $templateStart + $offset);

                $sins[] = $this->sinAt(
                    $line,
                    "Class override on <{$component}> - use semantic props instead",
                    $this->getSnippet($templateContent, $offset, 60),
                    "Add semantic props like 'variant' or 'fullWidth' to the component"
                );
            }
        }

        return empty($sins) ? $this->righteous() : $this->fallen($sins);
    }
}
