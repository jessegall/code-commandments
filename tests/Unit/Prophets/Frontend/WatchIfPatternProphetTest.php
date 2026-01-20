<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Frontend;

use JesseGall\CodeCommandments\Prophets\Frontend\WatchIfPatternProphet;
use JesseGall\CodeCommandments\Tests\TestCase;

class WatchIfPatternProphetTest extends TestCase
{
    private WatchIfPatternProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new WatchIfPatternProphet();
    }

    public function test_detects_watch_with_if_condition(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
watch(isReady, (value) => {
    if (value) {
        doSomething()
    }
})
</script>

<template>
  <div>Test</div>
</template>
VUE;

        $judgment = $this->prophet->judge('/test/Component.vue', $content);

        $this->assertTrue($judgment->hasWarnings());
    }

    public function test_passes_whenever_usage(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
import { whenever } from '@vueuse/core'

whenever(isReady, () => {
    doSomething()
})
</script>

<template>
  <div>Test</div>
</template>
VUE;

        $judgment = $this->prophet->judge('/test/Component.vue', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_passes_watch_without_if(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
watch(counter, (newValue) => {
    console.log('Counter changed to', newValue)
})
</script>

<template>
  <div>Test</div>
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
