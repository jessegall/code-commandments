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

    public function test_does_not_flag_a_guard_on_unrelated_state(): void
    {
        // Issue #19: the if condition is not the watched value — converting to
        // whenever() would change behaviour.
        $content = <<<'VUE'
        <script setup lang="ts">
        watch(editor.nodes, (nodes) => {
            if (current === null) return;
            build(nodes)
        })
        </script>
        VUE;

        $this->assertTrue($this->prophet->judge('/test/Component.vue', $content)->isRighteous());
    }

    public function test_does_not_flag_a_guard_on_a_falsy_unrelated_value(): void
    {
        // `if (!somethingElse)` is neither the watched param nor an early guard
        // on it — leave it.
        $content = <<<'VUE'
        <script setup lang="ts">
        watch(open, (isOpen) => {
            if (!somethingElse) act()
        })
        </script>
        VUE;

        $this->assertTrue($this->prophet->judge('/test/Component.vue', $content)->isRighteous());
    }

    public function test_flags_an_early_return_guard_on_the_watched_value(): void
    {
        // `if (!val) return` is the negated-guard form — convertible.
        $content = <<<'VUE'
        <script setup lang="ts">
        watch(ready, (val) => {
            if (!val) return;
            doIt()
        })
        </script>
        VUE;

        $this->assertTrue($this->prophet->judge('/test/Component.vue', $content)->hasWarnings());
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
