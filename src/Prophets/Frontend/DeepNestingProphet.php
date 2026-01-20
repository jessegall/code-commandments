<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Frontend;

use JesseGall\CodeCommandments\Commandments\FrontendCommandment;
use JesseGall\CodeCommandments\Results\Judgment;

/**
 * Avoid deeply nested templates - consider extracting components.
 *
 * Templates with deep nesting (6+ levels) are hard to read and maintain.
 */
class DeepNestingProphet extends FrontendCommandment
{
    private const DEEP_NESTING_THRESHOLD = 5;

    private const INDENT_SPACES = 4;

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

        $template = $this->extractTemplate($content);

        if ($template === null) {
            return $this->skip('No template section found');
        }

        $templateContent = $template['content'];
        $lines = explode("\n", $templateContent);
        $deepLines = 0;

        foreach ($lines as $line) {
            // Count leading spaces
            $leadingSpaces = strlen($line) - strlen(ltrim($line));
            $indentLevel = (int) ($leadingSpaces / self::INDENT_SPACES);

            // Check if line starts an element and is deeply nested
            if ($indentLevel >= self::DEEP_NESTING_THRESHOLD && preg_match('/^\s*<[a-zA-Z]/', $line)) {
                $deepLines++;
            }
        }

        if ($deepLines > 5) {
            return Judgment::withWarnings([
                $this->warningAt(
                    1,
                    "{$deepLines} deeply nested elements - consider component extraction",
                    'Extract nested sections into separate components'
                ),
            ]);
        }

        return $this->righteous();
    }
}
