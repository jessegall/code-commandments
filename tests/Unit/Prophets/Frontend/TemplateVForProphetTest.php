<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Frontend;

use JesseGall\CodeCommandments\Prophets\Frontend\TemplateVForProphet;
use JesseGall\CodeCommandments\Tests\TestCase;

class TemplateVForProphetTest extends TestCase
{
    private TemplateVForProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new TemplateVForProphet();
    }

    public function test_detects_v_for_on_element(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
const items = ref([])
</script>

<template>
    <div v-for="item in items" :key="item.id">
        {{ item.name }}
    </div>
</template>
VUE;

        $judgment = $this->prophet->judge('/test.vue', $content);

        $this->assertTrue($judgment->isFallen());
    }

    public function test_detects_v_for_on_li(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
const items = ref([])
</script>

<template>
    <ul>
        <li v-for="item in items" :key="item.id">{{ item.name }}</li>
    </ul>
</template>
VUE;

        $judgment = $this->prophet->judge('/test.vue', $content);

        $this->assertTrue($judgment->isFallen());
    }

    public function test_passes_with_template_wrapper(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
const items = ref([])
</script>

<template>
    <template v-for="item in items" :key="item.id">
        <div>{{ item.name }}</div>
    </template>
</template>
VUE;

        $judgment = $this->prophet->judge('/test.vue', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_skips_non_vue_files(): void
    {
        $content = '<div v-for="item in items">{{ item }}</div>';

        $judgment = $this->prophet->judge('/test.html', $content);

        $this->assertTrue($judgment->isRighteous());
    }
}
