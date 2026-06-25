<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Frontend;

use JesseGall\CodeCommandments\Commandments\FrontendCommandment;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Support\Pipes\Vue\VuePipeline;

/**
 * Use <script setup> Composition API instead of Options API.
 *
 * Always use the Composition API with <script setup lang="ts">.
 */
class CompositionApiProphet extends FrontendCommandment
{
    public function applicableExtensions(): array
    {
        return ['vue'];
    }

    public function description(): string
    {
        return 'Use <script setup> Composition API instead of Options API';
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
Always use the Composition API with <script setup lang="ts">.

Never use the Options API (export default { ... }). The Composition API
provides better TypeScript support and cleaner component organization.

Bad:
    <script>
    export default {
        data() { return { count: 0 } },
        methods: { increment() { this.count++ } }
    }
    </script>

Good:
    <script setup lang="ts">
    const count = ref(0);
    function increment() { count.value++; }
    </script>
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        if ($this->shouldSkipExtension($filePath)) {
            return $this->righteous();
        }

        return VuePipeline::make($filePath, $content)
            // Skip if no Options API pattern
            ->returnRighteousIfContentMatches('/^(?!.*export default \{)/s')
            // Skip if using defineComponent (still Composition API)
            ->returnRighteousIfContentMatches('/defineComponent/')
            // Skip if using <script setup>
            ->returnRighteousIfContentMatches('/<script\s+setup/')
            ->mapToSins(fn () => $this->sinAt(
                1,
                'Options API detected (export default { ... })',
                null,
                'Use <script setup lang="ts"> with Composition API instead'
            ))
            ->judge();
    }
}
