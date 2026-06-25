<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Frontend;

use JesseGall\CodeCommandments\Commandments\FrontendCommandment;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Support\Pipes\Vue\VueContext;
use JesseGall\CodeCommandments\Support\Pipes\Vue\VuePipeline;

/**
 * Components with slots must use defineSlots for type safety.
 *
 * When a component defines slots in its template, it should use
 * defineSlots to provide TypeScript typing for those slots.
 */
class MultipleSlotDefinitionsProphet extends FrontendCommandment
{
    public function applicableExtensions(): array
    {
        return ['vue'];
    }

    public function description(): string
    {
        return 'Components with slots must use defineSlots for type safety';
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
Components with scoped slots (slots that pass props) should use defineSlots
for type safety. This ensures consumers know what props each slot receives.

Simple slots without props don't require defineSlots.

Bad (scoped slot without defineSlots):
    <template>
      <div v-for="item in items">
        <slot name="item" :item="item" :index="i"></slot>
      </div>
    </template>

Good:
    <script setup lang="ts">
    defineSlots<{
      item: (props: { item: Item; index: number }) => void
    }>()
    </script>

    <template>
      <div v-for="item in items">
        <slot name="item" :item="item" :index="i"></slot>
      </div>
    </template>

Fine (simple slots, no props passed):
    <template>
      <slot name="header"></slot>
      <slot></slot>
    </template>
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        if ($this->shouldSkipExtension($filePath)) {
            return $this->righteous();
        }

        return VuePipeline::make($filePath, $content)
            ->onlyComponentFiles()
            ->extractTemplate()
            ->returnRighteousIfNoTemplate()
            ->returnRighteousWhen(fn (VueContext $ctx) => ! $this->hasScopedSlots($ctx->template['content']))
            ->extractScript()
            ->returnRighteousIfNoScript()
            ->returnRighteousIfSectionMatches('/defineSlots\s*[<(]/', 'script')
            ->mapToSins(fn (VueContext $ctx) => $this->sinAt(
                1,
                'Component has scoped slots but does not use defineSlots',
                null,
                'Add defineSlots<{ ... }>() to type your scoped slot props'
            ))
            ->judge();
    }

    /**
     * Check if template has scoped slots (slots with bound properties).
     */
    private function hasScopedSlots(string $templateContent): bool
    {
        // Match <slot with bound props like :prop="value" or v-bind:prop="value" or v-bind="obj"
        // But not just <slot> or <slot name="foo">
        return (bool) preg_match('/<slot[^>]*\s(:[a-zA-Z]|v-bind)[^>]*>/', $templateContent);
    }
}
