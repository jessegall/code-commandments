<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Frontend;

use JesseGall\CodeCommandments\Prophets\Frontend\LongVueFilesProphet;
use JesseGall\CodeCommandments\Tests\TestCase;

class LongVueFilesProphetTest extends TestCase
{
    private LongVueFilesProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new LongVueFilesProphet();
    }

    public function test_detects_vue_file_over_200_lines(): void
    {
        // Generate 210 lines of content
        $lines = array_fill(0, 210, '<div>Line</div>');
        $templateContent = implode("\n", $lines);

        $content = <<<VUE
<script setup lang="ts">
const x = 1;
</script>

<template>
{$templateContent}
</template>
VUE;

        $judgment = $this->prophet->judge('/test/Component.vue', $content);

        $this->assertTrue($judgment->hasWarnings());
    }

    public function test_passes_vue_file_under_200_lines(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
const x = 1;
</script>

<template>
  <div>Test</div>
</template>
VUE;

        $judgment = $this->prophet->judge('/test/Component.vue', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_passes_exactly_200_lines(): void
    {
        // Generate exactly 195 lines of content (leaving room for script and template tags)
        $lines = array_fill(0, 195, '<div>Line</div>');
        $templateContent = implode("\n", $lines);

        $content = <<<VUE
<script setup lang="ts">
</script>

<template>
{$templateContent}
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
        $this->assertStringContainsString('200', $this->prophet->description());
    }
}
