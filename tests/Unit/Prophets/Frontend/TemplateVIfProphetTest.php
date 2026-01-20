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

    public function test_repent_wraps_v_if_in_template(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
const show = ref(true)
</script>

<template>
    <div v-if="show">Content</div>
</template>
VUE;

        $result = $this->prophet->repent('/test.vue', $content);

        $this->assertTrue($result->absolved);
        $this->assertNotEmpty($result->penance);

        // Should have template wrapper
        $this->assertStringContainsString('<template v-if="show">', $result->newContent);
        // Should have clean div without v-if
        $this->assertStringContainsString('<div>Content</div>', $result->newContent);
        // Should NOT have duplicate closing tags or leftover v-if on div
        $this->assertStringNotContainsString('<div v-if=', $result->newContent);

        // Verify the result is now righteous
        $judgment = $this->prophet->judge('/test.vue', $result->newContent);
        $this->assertTrue($judgment->isRighteous(), "Repented content should be righteous. Got: " . $result->newContent);
    }

    public function test_repent_does_not_leave_orphan_closing_tags(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
const show = ref(true)
</script>

<template>
    <div v-if="show">
        <span>Inner content</span>
    </div>
    <p>After</p>
</template>
VUE;

        $result = $this->prophet->repent('/test.vue', $content);

        $this->assertTrue($result->absolved);

        // Count div tags - should have equal open and close
        $openDivCount = preg_match_all('/<div[^>]*>/', $result->newContent);
        $closeDivCount = preg_match_all('/<\/div>/', $result->newContent);
        $this->assertEquals($openDivCount, $closeDivCount, "Mismatched div tags in output:\n" . $result->newContent);

        // Count template tags - should have equal open and close
        $openTemplateCount = preg_match_all('/<template[^>]*>/', $result->newContent);
        $closeTemplateCount = preg_match_all('/<\/template>/', $result->newContent);
        $this->assertEquals($openTemplateCount, $closeTemplateCount, "Mismatched template tags in output:\n" . $result->newContent);

        // Verify the result is now righteous
        $judgment = $this->prophet->judge('/test.vue', $result->newContent);
        $this->assertTrue($judgment->isRighteous(), "Repented content should be righteous. Got:\n" . $result->newContent);
    }
}
