<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Frontend;

use JesseGall\CodeCommandments\Prophets\Frontend\SwitchCheckboxVModelProphet;
use JesseGall\CodeCommandments\Tests\TestCase;

class SwitchCheckboxVModelProphetTest extends TestCase
{
    private SwitchCheckboxVModelProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new SwitchCheckboxVModelProphet();
    }

    public function test_detects_v_model_checked_on_switch(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
</script>

<template>
  <Switch v-model:checked="form.enabled" />
</template>
VUE;

        $judgment = $this->prophet->judge('/test/Component.vue', $content);

        $this->assertTrue($judgment->isFallen());
        $this->assertGreaterThan(0, $judgment->sinCount());
    }

    public function test_detects_v_model_checked_on_checkbox(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
</script>

<template>
  <Checkbox v-model:checked="form.active" />
</template>
VUE;

        $judgment = $this->prophet->judge('/test/Component.vue', $content);

        $this->assertTrue($judgment->isFallen());
    }

    public function test_detects_colon_checked_on_switch(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
</script>

<template>
  <Switch :checked="value" @update:checked="value = $event" />
</template>
VUE;

        $judgment = $this->prophet->judge('/test/Component.vue', $content);

        $this->assertTrue($judgment->isFallen());
    }

    public function test_passes_v_model_on_switch(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
</script>

<template>
  <Switch v-model="form.enabled" />
</template>
VUE;

        $judgment = $this->prophet->judge('/test/Component.vue', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_passes_v_model_on_checkbox(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
</script>

<template>
  <Checkbox v-model="form.active" />
</template>
VUE;

        $judgment = $this->prophet->judge('/test/Component.vue', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_passes_checked_on_other_components(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
</script>

<template>
  <MyCustomComponent :checked="value" />
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
