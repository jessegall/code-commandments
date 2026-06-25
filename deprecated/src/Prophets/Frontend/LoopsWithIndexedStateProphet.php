<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Frontend;

use JesseGall\CodeCommandments\Commandments\FrontendCommandment;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\Warning;
use JesseGall\CodeCommandments\Support\Pipes\Vue\VueContext;
use JesseGall\CodeCommandments\Support\Pipes\Vue\VuePipeline;

/**
 * Extract loop items with indexed state to separate components.
 *
 * When a v-for loop body accesses indexed state (forms[item.id], items[index]),
 * extract the loop body into a separate component.
 */
class LoopsWithIndexedStateProphet extends FrontendCommandment
{
    public function applicableExtensions(): array
    {
        return ['vue'];
    }

    public function requiresConfession(): bool
    {
        return true;
    }

    public function description(): string
    {
        return 'Extract loop items with indexed state to separate components';
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
When a v-for loop body accesses indexed state (forms[item.id], items[index]),
extract the loop body into a separate component.

This prevents state management complexity and improves reactivity handling.

Bad:
    <template v-for="item in items" :key="item.id">
        <input v-model="forms[item.id].name" />
    </template>

Good:
    <template v-for="item in items" :key="item.id">
        <ItemForm :item="item" @save="handleSave" />
    </template>

The extracted component manages its own local state.
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        if ($this->shouldSkipExtension($filePath)) {
            return $this->righteous();
        }

        return VuePipeline::make($filePath, $content)
            ->inTemplate()
            ->returnRighteousWhen(fn (VueContext $ctx) => !str_contains($ctx->getSectionContent(), 'v-for='))
            ->matchAll('/\[[a-zA-Z]+\.(id|key)\]|\[[a-zA-Z]+\]\.|\[index\]/')
            ->mapToWarnings(fn (VueContext $ctx) => array_map(
                fn ($match) => Warning::at(
                    $match->line,
                    'Indexed state access in v-for loop - consider extracting to component',
                    'Extract the loop body into a separate component that manages its own state'
                ),
                $ctx->matches
            ))
            ->judge();
    }
}
