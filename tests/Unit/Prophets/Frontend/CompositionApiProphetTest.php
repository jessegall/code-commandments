<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Frontend;

use JesseGall\CodeCommandments\Prophets\Frontend\CompositionApiProphet;
use JesseGall\CodeCommandments\Tests\TestCase;

class CompositionApiProphetTest extends TestCase
{
    private CompositionApiProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new CompositionApiProphet();
    }

    public function test_detects_options_api(): void
    {
        $content = $this->getFixtureContent('Frontend', 'Sinful', 'OptionsApiComponent.vue');
        $judgment = $this->prophet->judge('/test/Component.vue', $content);

        $this->assertTrue($judgment->isFallen());
        $this->assertGreaterThan(0, $judgment->sinCount());
    }

    public function test_passes_composition_api(): void
    {
        $content = $this->getFixtureContent('Frontend', 'Righteous', 'CompositionApiComponent.vue');
        $judgment = $this->prophet->judge('/test/Component.vue', $content);

        $this->assertTrue($judgment->isRighteous());
        $this->assertEquals(0, $judgment->sinCount());
    }

    public function test_detects_data_function(): void
    {
        $content = <<<'VUE'
<script>
export default {
  data() {
    return { count: 0 }
  }
}
</script>

<template>
  <div>{{ count }}</div>
</template>
VUE;

        $judgment = $this->prophet->judge('/test/Component.vue', $content);

        $this->assertTrue($judgment->isFallen());
    }

    public function test_detects_methods_option(): void
    {
        $content = <<<'VUE'
<script>
export default {
  methods: {
    handleClick() {
      console.log('clicked')
    }
  }
}
</script>

<template>
  <button @click="handleClick">Click</button>
</template>
VUE;

        $judgment = $this->prophet->judge('/test/Component.vue', $content);

        $this->assertTrue($judgment->isFallen());
    }

    public function test_passes_script_setup(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
import { ref } from 'vue'

const count = ref(0)
</script>

<template>
  <div>{{ count }}</div>
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
