<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Frontend;

use JesseGall\CodeCommandments\Prophets\Frontend\MultipleSlotDefinitionsProphet;
use JesseGall\CodeCommandments\Tests\TestCase;

class MultipleSlotDefinitionsProphetTest extends TestCase
{
    private MultipleSlotDefinitionsProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new MultipleSlotDefinitionsProphet();
    }

    public function test_detects_missing_define_slots(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
// No defineSlots
</script>

<template>
  <div>
    <slot name="header"></slot>
    <slot></slot>
  </div>
</template>
VUE;

        $judgment = $this->prophet->judge('/components/Card.vue', $content);

        $this->assertTrue($judgment->isFallen());
        $this->assertGreaterThan(0, $judgment->sinCount());
    }

    public function test_passes_with_define_slots(): void
    {
        $content = <<<'VUE'
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
VUE;

        $judgment = $this->prophet->judge('/components/Card.vue', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_passes_without_slots(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
// No slots, no defineSlots needed
</script>

<template>
  <div>
    <p>No slots here</p>
  </div>
</template>
VUE;

        $judgment = $this->prophet->judge('/components/Card.vue', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_skips_non_component_files(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
// No defineSlots - but this is a page, not a component
</script>

<template>
  <div>
    <slot name="header"></slot>
    <slot></slot>
  </div>
</template>
VUE;

        $judgment = $this->prophet->judge('/pages/Home.vue', $content);

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
