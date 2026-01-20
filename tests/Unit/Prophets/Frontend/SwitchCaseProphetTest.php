<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Frontend;

use JesseGall\CodeCommandments\Prophets\Frontend\SwitchCaseProphet;
use JesseGall\CodeCommandments\Tests\TestCase;

class SwitchCaseProphetTest extends TestCase
{
    private SwitchCaseProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new SwitchCaseProphet();
    }

    public function test_detects_v_if_chains_comparing_same_variable(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
const status = ref('pending');
</script>

<template>
  <div v-if="status === 'pending'">Pending...</div>
  <div v-else-if="status === 'success'">Success!</div>
  <div v-else-if="status === 'error'">Error</div>
  <div v-else>Unknown</div>
</template>
VUE;

        $judgment = $this->prophet->judge('/test/Component.vue', $content);

        $this->assertTrue($judgment->isFallen());
        $this->assertGreaterThan(0, $judgment->sinCount());
    }

    public function test_detects_complex_condition_chains(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
</script>

<template>
  <div v-if="a === 'x' && b === 'y'">First</div>
  <div v-else-if="c || d">Second</div>
  <div v-else-if="e && f">Third</div>
  <div v-else>Default</div>
</template>
VUE;

        $judgment = $this->prophet->judge('/test/Component.vue', $content);

        $this->assertTrue($judgment->isFallen());
    }

    public function test_passes_switch_case_component(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
const status = ref('pending');
</script>

<template>
  <SwitchCase :value="status">
    <template #pending>Pending...</template>
    <template #success>Success!</template>
    <template #error>Error</template>
  </SwitchCase>
</template>
VUE;

        $judgment = $this->prophet->judge('/test/Component.vue', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_passes_simple_v_if_else(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
const isLoading = ref(false);
</script>

<template>
  <div v-if="isLoading">Loading...</div>
  <div v-else>Content</div>
</template>
VUE;

        $judgment = $this->prophet->judge('/test/Component.vue', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_passes_two_case_chain(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
const status = ref('pending');
</script>

<template>
  <div v-if="status === 'pending'">Pending...</div>
  <div v-else-if="status === 'success'">Success!</div>
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
