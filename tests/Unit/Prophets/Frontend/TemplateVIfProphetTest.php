<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Frontend;

use JesseGall\CodeCommandments\Prophets\Frontend\TemplateVIfProphet;
use JesseGall\CodeCommandments\Tests\TestCase;

class TemplateVIfProphetTest extends TestCase
{
    private TemplateVIfProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new TemplateVIfProphet();
    }

    public function test_detects_v_if_on_element(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
const show = ref(true)
</script>

<template>
    <div v-if="show">Content</div>
</template>
VUE;

        $judgment = $this->prophet->judge('/test.vue', $content);

        $this->assertTrue($judgment->isFallen());
        $this->assertEquals(1, $judgment->sinCount());
    }

    public function test_detects_v_else_if_on_element(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
const type = ref('a')
</script>

<template>
    <template v-if="type === 'a'">
        <div>A</div>
    </template>
    <span v-else-if="type === 'b'">B</span>
</template>
VUE;

        $judgment = $this->prophet->judge('/test.vue', $content);

        $this->assertTrue($judgment->isFallen());
    }

    public function test_passes_with_template_wrapper(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
const show = ref(true)
</script>

<template>
    <template v-if="show">
        <div>Content</div>
    </template>
    <template v-else>
        <div>Other</div>
    </template>
</template>
VUE;

        $judgment = $this->prophet->judge('/test.vue', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_skips_non_vue_files(): void
    {
        $content = '<div v-if="show">Content</div>';

        $judgment = $this->prophet->judge('/test.html', $content);

        $this->assertTrue($judgment->isRighteous());
    }
}
