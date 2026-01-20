<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Frontend;

use JesseGall\CodeCommandments\Commandments\FrontendCommandment;
use JesseGall\CodeCommandments\Results\Judgment;

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

        // Check for Options API pattern: export default {
        if (!preg_match('/export default \{/', $content)) {
            return $this->righteous();
        }

        // Skip defineComponent (still Composition API technically)
        if (str_contains($content, 'defineComponent')) {
            return $this->righteous();
        }

        // If the file has <script setup, it's using Composition API
        // The export default is likely just for layout definition
        if (preg_match('/<script\s+setup/', $content)) {
            return $this->righteous();
        }

        return $this->fallen([
            $this->sinAt(
                1,
                'Options API detected (export default { ... })',
                null,
                'Use <script setup lang="ts"> with Composition API instead'
            ),
        ]);
    }
}
