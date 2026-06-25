<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Frontend;

use JesseGall\CodeCommandments\Commandments\FrontendCommandment;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Support\Pipes\Vue\VueContext;
use JesseGall\CodeCommandments\Support\Pipes\Vue\VuePipeline;

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

        return VuePipeline::make($filePath, $content)
            ->inTemplate()
            ->mapToWarnings(function (VueContext $ctx) {
                $templateContent = $ctx->getSectionContent();

                $namedSlotCount = preg_match_all('/<template\s+#(?!default)[a-zA-Z]/', $templateContent);
                $hasExplicitDefault = preg_match('/<template\s+#default/', $templateContent);

                // Only flag REAL default content sitting as a SIBLING of a named
                // slot — non-whitespace text right after a named slot's closing
                // </template>. Text INSIDE a slot's own content, and whitespace
                // between/around named slots, is not implicit default (#21).
                if ($namedSlotCount >= 1 && !$hasExplicitDefault && $this->hasSiblingDefaultContent($templateContent)) {
                    return $this->warningAt(
                        1,
                        'Using named slot(s) with implicit default content',
                        'Use explicit <template #default> when using more than one slot'
                    );
                }

                return null;
            })
            ->judge();
    }

    /**
     * Whether the template has non-whitespace default content as a SIBLING of a
     * named slot — i.e. raw text immediately after a named slot's closing
     * </template>, or raw text directly before a named slot opens. Content
     * inside a slot's own body, and inter-slot whitespace, do not count.
     */
    private function hasSiblingDefaultContent(string $template): bool
    {
        // Text right after a named slot closes: `</template>  Some text`.
        if (preg_match('/<\/template>\s*[^<\s]/', $template)) {
            return true;
        }

        // Raw text directly before a named slot opens, as a direct child:
        // `<Component>  Some text  <template #name>` (no element in between).
        return (bool) preg_match('/>\s*[^<\s][^<]*?<template\s+#(?!default)[a-zA-Z]/', $template);
    }
}
