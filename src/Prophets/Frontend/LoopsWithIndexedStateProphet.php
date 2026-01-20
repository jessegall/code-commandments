<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Frontend;

use JesseGall\CodeCommandments\Commandments\FrontendCommandment;
use JesseGall\CodeCommandments\Results\Judgment;

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

        $template = $this->extractTemplate($content);

        if ($template === null) {
            return $this->skip('No template section found');
        }

        $templateContent = $template['content'];
        $templateStart = $template['start'];

        // Check if file has v-for and indexed state access patterns
        if (!str_contains($templateContent, 'v-for=')) {
            return $this->righteous();
        }

        // Look for indexed state access patterns: [item.id], [item.key], [variable]., [index]
        $indexedStatePattern = '/\[[a-zA-Z]+\.(id|key)\]|\[[a-zA-Z]+\]\.|\[index\]/';

        if (preg_match($indexedStatePattern, $templateContent, $match, PREG_OFFSET_CAPTURE)) {
            $offset = $match[0][1];
            $line = $this->getLineFromOffset($content, $templateStart + $offset);

            return Judgment::withWarnings([
                $this->warningAt(
                    $line,
                    'Indexed state access in v-for loop - consider extracting to component',
                    'Extract the loop body into a separate component that manages its own state'
                ),
            ]);
        }

        return $this->righteous();
    }
}
