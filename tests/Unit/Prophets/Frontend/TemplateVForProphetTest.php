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

    public function test_repent_wraps_v_for_in_template(): void
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

        $result = $this->prophet->repent('/test.vue', $content);

        $this->assertTrue($result->absolved);
        $this->assertNotEmpty($result->penance);
        $this->assertStringContainsString('<template v-for="item in items"', $result->newContent);
    }

    public function test_repent_handles_nested_elements_correctly(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
const items = ref([])
</script>

<template>
    <div v-for="item in items" :key="item.id" class="outer">
        <div class="inner">
            {{ item.name }}
        </div>
        <span>After inner</span>
    </div>
</template>
VUE;

        $result = $this->prophet->repent('/test.vue', $content);

        $this->assertTrue($result->absolved);

        // Count div tags - should have equal open and close
        preg_match_all('/<div[^>]*>/', $result->newContent, $openDivs);
        preg_match_all('/<\/div>/', $result->newContent, $closeDivs);
        $this->assertCount(
            count($closeDivs[0]),
            $openDivs[0],
            "Mismatched div tags in output:\n" . $result->newContent
        );

        // Verify the result is righteous
        $judgment = $this->prophet->judge('/test.vue', $result->newContent);
        $this->assertTrue($judgment->isRighteous(), "Repented content should be righteous:\n" . $result->newContent);
    }
}
