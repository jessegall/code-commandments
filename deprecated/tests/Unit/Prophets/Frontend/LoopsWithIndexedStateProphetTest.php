<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Frontend;

use JesseGall\CodeCommandments\Prophets\Frontend\LoopsWithIndexedStateProphet;
use JesseGall\CodeCommandments\Tests\TestCase;

class LoopsWithIndexedStateProphetTest extends TestCase
{
    private LoopsWithIndexedStateProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new LoopsWithIndexedStateProphet();
    }

    public function test_detects_indexed_state_with_item_id(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
</script>

<template>
  <template v-for="item in items" :key="item.id">
    <input v-model="forms[item.id].name" />
  </template>
</template>
VUE;

        $judgment = $this->prophet->judge('/test/Component.vue', $content);

        $this->assertTrue($judgment->hasWarnings());
    }

    public function test_detects_indexed_state_with_item_key(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
</script>

<template>
  <template v-for="item in items" :key="item.key">
    <input v-model="state[item.key].value" />
  </template>
</template>
VUE;

        $judgment = $this->prophet->judge('/test/Component.vue', $content);

        $this->assertTrue($judgment->hasWarnings());
    }

    public function test_detects_indexed_state_with_index(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
</script>

<template>
  <template v-for="(item, index) in items" :key="index">
    <input v-model="values[index]" />
  </template>
</template>
VUE;

        $judgment = $this->prophet->judge('/test/Component.vue', $content);

        $this->assertTrue($judgment->hasWarnings());
    }

    public function test_passes_simple_v_for_without_indexed_state(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
</script>

<template>
  <template v-for="item in items" :key="item.id">
    <ItemCard :item="item" @save="handleSave" />
  </template>
</template>
VUE;

        $judgment = $this->prophet->judge('/test/Component.vue', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_passes_without_v_for(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
</script>

<template>
  <div>No loops here</div>
</template>
VUE;

        $judgment = $this->prophet->judge('/test/Component.vue', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_requires_confession(): void
    {
        $this->assertTrue($this->prophet->requiresConfession());
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
