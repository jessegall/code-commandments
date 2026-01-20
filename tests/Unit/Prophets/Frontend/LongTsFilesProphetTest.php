<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Frontend;

use JesseGall\CodeCommandments\Prophets\Frontend\LongTsFilesProphet;
use JesseGall\CodeCommandments\Tests\TestCase;

class LongTsFilesProphetTest extends TestCase
{
    private LongTsFilesProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new LongTsFilesProphet();
    }

    public function test_detects_script_over_200_lines(): void
    {
        // Generate 210 lines of script content
        $lines = array_fill(0, 210, 'const x = 1;');
        $scriptContent = implode("\n", $lines);

        $content = <<<VUE
<script setup lang="ts">
{$scriptContent}
</script>

<template>
  <div>Test</div>
</template>
VUE;

        $judgment = $this->prophet->judge('/test/Component.vue', $content);

        $this->assertTrue($judgment->hasWarnings());
    }

    public function test_passes_script_under_200_lines(): void
    {
        // Generate 50 lines of script content
        $lines = array_fill(0, 50, 'const x = 1;');
        $scriptContent = implode("\n", $lines);

        $content = <<<VUE
<script setup lang="ts">
{$scriptContent}
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
        // Generate exactly 200 lines of script content
        $lines = array_fill(0, 200, 'const x = 1;');
        $scriptContent = implode("\n", $lines);

        $content = <<<VUE
<script setup lang="ts">
{$scriptContent}
</script>

<template>
  <div>Test</div>
</template>
VUE;

        $judgment = $this->prophet->judge('/test/Component.vue', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_skips_files_without_script(): void
    {
        $content = <<<'VUE'
<template>
  <div>Test</div>
</template>
VUE;

        $judgment = $this->prophet->judge('/test/Component.vue', $content);

        $this->assertTrue($judgment->skipped);
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
