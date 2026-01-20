<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Frontend;

use JesseGall\CodeCommandments\Commandments\FrontendCommandment;
use JesseGall\CodeCommandments\Results\Judgment;

/**
 * Keep Vue files under max lines by extracting components.
 *
 * Vue components should be kept under a configurable line limit.
 */
class LongVueFilesProphet extends FrontendCommandment
{
    public function applicableExtensions(): array
    {
        return ['vue'];
    }

    public function description(): string
    {
        $maxLines = $this->getMaxLines();

        return "Keep Vue files under {$maxLines} lines by extracting components";
    }

    public function detailedDescription(): string
    {
        $maxLines = $this->getMaxLines();

        return <<<SCRIPTURE
Vue components should be kept under {$maxLines} lines.

Large components indicate a need for extraction. Split into:
- Child components for distinct UI sections
- Composables for reusable logic
- Separate files for types/interfaces

Signs you need to extract:
- Multiple unrelated features in one file
- Repeated patterns that could be abstracted
- Complex template sections with their own state
- More than 5-6 functions in script section
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        if ($this->shouldSkipExtension($filePath)) {
            return $this->righteous();
        }

        $maxLines = $this->getMaxLines();
        $lineCount = substr_count($content, "\n") + 1;

        if ($lineCount > $maxLines) {
            return Judgment::withWarnings([
                $this->warningAt(
                    1,
                    "{$lineCount} lines - review for potential component extraction",
                    "Keep Vue files under {$maxLines} lines"
                ),
            ]);
        }

        return $this->righteous();
    }

    private function getMaxLines(): int
    {
        return (int) $this->config('max_vue_lines', 200);
    }
}
