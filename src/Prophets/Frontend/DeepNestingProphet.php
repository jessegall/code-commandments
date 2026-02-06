<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Frontend;

use JesseGall\CodeCommandments\Commandments\FrontendCommandment;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Support\Pipes\Vue\VueContext;
use JesseGall\CodeCommandments\Support\Pipes\Vue\VuePipeline;

/**
 * Avoid deeply nested templates - consider extracting components.
 *
 * Templates with deep nesting (6+ levels) are hard to read and maintain.
 */
class DeepNestingProphet extends FrontendCommandment
{
    public function requiresConfession(): bool
    {
        return true;
    }

    public function applicableExtensions(): array
    {
        return ['vue'];
    }

    public function description(): string
    {
        return 'Avoid deeply nested templates - consider extracting components';
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
Templates with deep nesting (6+ levels) are hard to read and maintain.
Consider extracting nested sections into separate components.

Signs of deep nesting:
- Multiple nested v-for loops
- Complex conditional structures
- Many wrapper elements

Solutions:
- Extract list items into separate components
- Use composition to break up complex templates
- Create presentational components for nested structures
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        if ($this->shouldSkipExtension($filePath)) {
            return $this->righteous();
        }

        return VuePipeline::make($filePath, $content)
            ->inTemplate()
            ->mapToWarnings(function (VueContext $ctx) {
                $templateContent = $ctx->getSectionContent();
                $lines = explode("\n", $templateContent);
                $deepLines = 0;

                $maxNestingDepth = (int) $this->config('max_nesting_depth', 5);
                $indentSpaces = (int) $this->config('indent_spaces', 4);
                $minDeepLines = (int) $this->config('min_deep_lines', 5);

                foreach ($lines as $line) {
                    $leadingSpaces = strlen($line) - strlen(ltrim($line));
                    $indentLevel = (int) ($leadingSpaces / $indentSpaces);

                    if ($indentLevel >= $maxNestingDepth && preg_match('/^\s*<[a-zA-Z]/', $line)) {
                        $deepLines++;
                    }
                }

                if ($deepLines > $minDeepLines) {
                    return $this->warningAt(
                        1,
                        "{$deepLines} deeply nested elements - consider component extraction",
                        'Extract nested sections into separate components'
                    );
                }

                return null;
            })
            ->judge();
    }
}
