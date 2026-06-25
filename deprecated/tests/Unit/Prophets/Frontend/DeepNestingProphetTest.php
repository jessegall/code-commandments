<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Frontend;

use JesseGall\CodeCommandments\Prophets\Frontend\DeepNestingProphet;
use JesseGall\CodeCommandments\Tests\TestCase;

class DeepNestingProphetTest extends TestCase
{
    private DeepNestingProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new DeepNestingProphet();
    }

    public function test_detects_deep_nesting(): void
    {
        // Using 4 spaces per indent, need >= 5 indent levels (20+ spaces) for more than 5 lines
        // The prophet checks: $indentLevel >= DEEP_NESTING_THRESHOLD (5) AND $deepLines > 5
        $content = <<<'VUE'
<script setup lang="ts">
</script>

<template>
    <div>
        <div>
            <div>
                <div>
                    <div>
                        <div>Element 1</div>
                        <div>Element 2</div>
                        <div>Element 3</div>
                        <div>Element 4</div>
                        <div>Element 5</div>
                        <div>Element 6</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
VUE;

        $judgment = $this->prophet->judge('/test/Component.vue', $content);

        $this->assertTrue($judgment->hasWarnings());
    }

    public function test_passes_shallow_nesting(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
</script>

<template>
    <div>
        <div>
            <span>Not too deep</span>
        </div>
    </div>
</template>
VUE;

        $judgment = $this->prophet->judge('/test/Component.vue', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_passes_flat_structure(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
</script>

<template>
    <div>Content</div>
</template>
VUE;

        $judgment = $this->prophet->judge('/test/Component.vue', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_skips_non_vue_files(): void
    {
        $judgment = $this->prophet->judge('/test/script.ts', 'const x = 1');

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_provides_helpful_description(): void
    {
        $this->assertNotEmpty($this->prophet->description());
        $this->assertNotEmpty($this->prophet->detailedDescription());
    }
}
