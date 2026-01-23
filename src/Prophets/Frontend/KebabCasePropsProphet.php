<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Frontend;

use JesseGall\CodeCommandments\Commandments\FrontendCommandment;
use JesseGall\CodeCommandments\Contracts\SinRepenter;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\RepentanceResult;

/**
 * Props should be bound using kebab-case in templates.
 *
 * Vue convention recommends kebab-case for prop bindings in templates
 * to maintain consistency with HTML attribute naming conventions.
 */
class KebabCasePropsProphet extends FrontendCommandment implements SinRepenter
{
    public function applicableExtensions(): array
    {
        return ['vue'];
    }

    public function description(): string
    {
        return 'Props should be bound using kebab-case in templates';
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
Props should be bound using kebab-case in Vue templates.

This follows Vue's recommended convention and maintains consistency
with HTML attribute naming conventions.

Bad:
    <MyComponent :someValue="data" />
    <MyComponent :userName="name" />

Good:
    <MyComponent :some-value="data" />
    <MyComponent :user-name="name" />

Note: This only applies to bound props (with :), not regular attributes.
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

        // Pattern to find camelCase prop bindings
        // Matches :propName=" or v-bind:propName=" where propName contains lowercase followed by uppercase
        $pattern = '/(?::|v-bind:)([a-z]+[A-Z][a-zA-Z]*)\s*=/';

        preg_match_all($pattern, $templateContent, $matches, PREG_OFFSET_CAPTURE);

        foreach ($matches[0] as $index => $match) {
            $fullMatch = $match[0];
            $position = $match[1];
            $propName = $matches[1][$index][0];

            $line = $this->getLineFromOffset($content, $templateStart + $position);
            $kebabCase = $this->toKebabCase($propName);

            $sins[] = $this->sinAt(
                $line,
                "Prop binding uses camelCase instead of kebab-case",
                trim($fullMatch),
                "Use :{$kebabCase}= instead of :{$propName}="
            );
        }

        return empty($sins) ? $this->righteous() : $this->fallen($sins);
    }

    /**
     * Convert camelCase to kebab-case.
     */
    protected function toKebabCase(string $value): string
    {
        return strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $value) ?? $value);
    }

    public function canRepent(string $filePath): bool
    {
        return pathinfo($filePath, PATHINFO_EXTENSION) === 'vue';
    }

    public function repent(string $filePath, string $content): RepentanceResult
    {
        if (!$this->canRepent($filePath)) {
            return RepentanceResult::unchanged();
        }

        $template = $this->extractTemplate($content);

        if ($template === null) {
            return RepentanceResult::unchanged();
        }

        $templateContent = $template['content'];
        $penance = [];

        // Pattern to find camelCase prop bindings
        $pattern = '/(:|v-bind:)([a-z]+[A-Z][a-zA-Z]*)(\s*=)/';

        $newTemplateContent = preg_replace_callback(
            $pattern,
            function ($matches) use (&$penance) {
                $prefix = $matches[1];
                $propName = $matches[2];
                $suffix = $matches[3];
                $kebabCase = $this->toKebabCase($propName);

                $penance[] = "Converted :{$propName} to :{$kebabCase}";

                return $prefix . $kebabCase . $suffix;
            },
            $templateContent
        );

        if ($newTemplateContent === $templateContent || empty($penance)) {
            return RepentanceResult::unchanged();
        }

        // Replace the template content in the original file
        $newContent = substr($content, 0, $template['start'])
            . $newTemplateContent
            . substr($content, $template['end']);

        return RepentanceResult::absolved($newContent, $penance);
    }
}
