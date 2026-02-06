<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Frontend;

use JesseGall\CodeCommandments\Commandments\FrontendCommandment;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\Warning;
use JesseGall\CodeCommandments\Support\Pipes\Vue\VueContext;
use JesseGall\CodeCommandments\Support\Pipes\Vue\VuePipeline;

/**
 * Keep Vue files under max lines by extracting components.
 *
 * Vue components should be kept under a configurable line limit.
 */
class LongVueFilesProphet extends FrontendCommandment
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

        return VuePipeline::make($filePath, $content)
            ->mapToWarnings(fn (VueContext $ctx) => $this->checkLineCount($ctx, $maxLines))
            ->judge();
    }

    private function checkLineCount(VueContext $ctx, int $maxLines): ?Warning
    {
        $lineCount = substr_count($ctx->content, "\n") + 1;

        if ($lineCount > $maxLines) {
            return Warning::at(
                line: 1,
                message: "{$lineCount} lines - review for potential component extraction",
                snippet: "Keep Vue files under {$maxLines} lines"
            );
        }

        return null;
    }

    private function getMaxLines(): int
    {
        return (int) $this->config('max_vue_lines', 200);
    }
}
