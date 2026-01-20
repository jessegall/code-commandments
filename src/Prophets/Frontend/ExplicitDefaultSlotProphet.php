<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Frontend;

use JesseGall\CodeCommandments\Commandments\FrontendCommandment;
use JesseGall\CodeCommandments\Results\Judgment;

/**
 * Use explicit <template #default> when using named slots.
 *
 * When a component uses named slots, also use explicit <template #default>
 * for default slot content instead of implicit content.
 */
class ExplicitDefaultSlotProphet extends FrontendCommandment
{
    public function applicableExtensions(): array
    {
        return ['vue'];
    }

    public function description(): string
    {
        return 'Use explicit <template #default> when using named slots';
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
When a component uses named slots, also use explicit <template #default>
for default slot content instead of implicit content.

Bad:
    <Card>
        <template #header>Title</template>
        Content here without explicit default slot
    </Card>

Good:
    <Card>
        <template #header>Title</template>
        <template #default>
            Content here with explicit default slot
        </template>
    </Card>

This makes the slot usage explicit and easier to understand.
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

        // Check if file uses named slots
        $hasNamedSlots = preg_match_all('/<template #[a-zA-Z]/', $templateContent);
        $hasDefaultSlot = preg_match('/<template #default/', $templateContent);

        if ($hasNamedSlots > 0 && !$hasDefaultSlot) {
            // Check if there's content that's not in a slot
            // This is a heuristic - check for text content between component tags
            if (preg_match('/>\s*[^<\s]/', $templateContent)) {
                return Judgment::withWarnings([
                    $this->warningAt(
                        1,
                        "Has {$hasNamedSlots} named slot(s) but no <template #default>",
                        'Use explicit <template #default> for default slot content'
                    ),
                ]);
            }
        }

        return $this->righteous();
    }
}
