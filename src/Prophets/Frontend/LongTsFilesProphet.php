<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Frontend;

use JesseGall\CodeCommandments\Commandments\FrontendCommandment;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Support\Pipes\Vue\VueContext;
use JesseGall\CodeCommandments\Support\Pipes\Vue\VuePipeline;

/**
 * TypeScript files in components should be under 200 lines.
 *
 * Large TypeScript files in components indicate the need to split logic.
 */
class LongTsFilesProphet extends FrontendCommandment
{
    private const DEFAULT_MAX_TS_LINES = 200;

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
        return 'TypeScript files in components should be under '.$this->getMaxTsLines().' lines';
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
Large TypeScript files in components indicate the need to split logic.
Keep component TypeScript under 200 lines by:

1. Extracting composables for reusable logic
2. Moving complex computations to utility functions
3. Splitting into smaller components
4. Using Pinia stores for shared state

Example extraction:
    // Before: All in component
    const items = ref([]);
    const search = ref('');
    const filteredItems = computed(() => ...complex filtering...);
    function sortItems() { ...complex sorting... }

    // After: Extract to composable
    // composables/useItemList.ts
    export function useItemList() {
        const items = ref([]);
        const search = ref('');
        const filteredItems = computed(() => ...);
        function sortItems() { ... }
        return { items, search, filteredItems, sortItems };
    }
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        if ($this->shouldSkipExtension($filePath)) {
            return $this->righteous();
        }

        $maxLines = $this->getMaxTsLines();

        return VuePipeline::make($filePath, $content)
            ->extractScript()
            ->skipIfNoScript()
            ->mapToWarnings(function (VueContext $ctx) use ($maxLines) {
                $scriptContent = $ctx->getSectionContent();
                $lineCount = substr_count($scriptContent, "\n") + 1;

                if ($lineCount > $maxLines) {
                    return $this->warningAt(
                        1,
                        "Script section has {$lineCount} lines (max: {$maxLines})",
                        'Extract logic to composables, utilities, or smaller components'
                    );
                }

                return null;
            })
            ->judge();
    }

    private function getMaxTsLines(): int
    {
        return (int) $this->config('max_ts_lines', self::DEFAULT_MAX_TS_LINES);
    }
}
