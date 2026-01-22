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
When using more than one slot, use explicit <template #default> for default content.

If only ONE slot is used (just default, or just one named slot), no explicit
#default is required. But when combining named slots with default content,
make the default explicit.

Bad (named slot + implicit default = 2 slots):
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

Also fine (only one slot used):
    <Card>
        <template #header>Title</template>
    </Card>

    <Card>
        Just default content, no named slots
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

        // Count named slots (excluding #default)
        $namedSlotCount = preg_match_all('/<template\s+#(?!default)[a-zA-Z]/', $templateContent);
        $hasExplicitDefault = preg_match('/<template\s+#default/', $templateContent);

        // If there are named slots and implicit default content (not wrapped in #default),
        // that means more than one slot is being used, so #default should be explicit
        if ($namedSlotCount >= 1 && !$hasExplicitDefault) {
            // Check if there's content that's not in a template slot
            // This is a heuristic - check for text content between component tags
            if (preg_match('/>\s*[^<\s]/', $templateContent)) {
                return Judgment::withWarnings([
                    $this->warningAt(
                        1,
                        'Using named slot(s) with implicit default content',
                        'Use explicit <template #default> when using more than one slot'
                    ),
                ]);
            }
        }

        return $this->righteous();
    }
}
