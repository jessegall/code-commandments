<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Frontend;

use JesseGall\CodeCommandments\Commandments\FrontendCommandment;
use JesseGall\CodeCommandments\Results\Judgment;

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
Components that define slots in their template should use defineSlots
for type safety. This ensures consumers know what slots are available
and what props each slot receives.

Bad:
    <template>
      <div>
        <slot name="header"></slot>
        <slot></slot>
      </div>
    </template>

Good:
    <script setup lang="ts">
    defineSlots<{
      header: () => void
      default: () => void
    }>()
    </script>

    <template>
      <div>
        <slot name="header"></slot>
        <slot></slot>
      </div>
    </template>
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        if ($this->shouldSkipExtension($filePath)) {
            return $this->righteous();
        }

        // Only check component files (in /components/ or /Components/ directories)
        if (!str_contains($filePath, '/components/') && !str_contains($filePath, '/Components/')) {
            return $this->righteous();
        }

        $template = $this->extractTemplate($content);

        if ($template === null) {
            return $this->skip('No template section found');
        }

        // Check if template has slot elements
        if (!preg_match('/<slot[\s>\/]/', $template['content'])) {
            return $this->righteous();
        }

        $script = $this->extractScript($content);

        if ($script === null) {
            return $this->skip('No script section found');
        }

        // Check if defineSlots is used
        if (!preg_match('/defineSlots\s*[<(]/', $script['content'])) {
            $line = $this->getLineFromOffset($content, $template['start']);

            return $this->fallen([
                $this->sinAt(
                    $line,
                    'Component has slots but does not use defineSlots',
                    null,
                    'Add defineSlots<{ ... }>() to type your slots'
                ),
            ]);
        }

        return $this->righteous();
    }
}
