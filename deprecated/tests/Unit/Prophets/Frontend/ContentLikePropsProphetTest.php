<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Frontend;

use JesseGall\CodeCommandments\Prophets\Frontend\ContentLikePropsProphet;
use JesseGall\CodeCommandments\Tests\TestCase;

class ContentLikePropsProphetTest extends TestCase
{
    private ContentLikePropsProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new ContentLikePropsProphet();
    }

    public function test_detects_long_title_prop(): void
    {
        $longTitle = str_repeat('a', 55);
        $content = <<<VUE
<script setup lang="ts">
</script>

<template>
  <Card title="{$longTitle}">Content</Card>
</template>
VUE;

        $judgment = $this->prophet->judge('/test/Component.vue', $content);

        $this->assertTrue($judgment->hasWarnings());
    }

    public function test_detects_long_description_prop(): void
    {
        $longDesc = str_repeat('b', 55);
        $content = <<<VUE
<script setup lang="ts">
</script>

<template>
  <Card description="{$longDesc}">Content</Card>
</template>
VUE;

        $judgment = $this->prophet->judge('/test/Component.vue', $content);

        $this->assertTrue($judgment->hasWarnings());
    }

    public function test_detects_long_label_prop(): void
    {
        $longLabel = str_repeat('c', 55);
        $content = <<<VUE
<script setup lang="ts">
</script>

<template>
  <Button label="{$longLabel}">Click</Button>
</template>
VUE;

        $judgment = $this->prophet->judge('/test/Component.vue', $content);

        $this->assertTrue($judgment->hasWarnings());
    }

    public function test_passes_short_content_props(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
</script>

<template>
  <Card title="Short Title" description="A brief description">
    Content
  </Card>
</template>
VUE;

        $judgment = $this->prophet->judge('/test/Component.vue', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_passes_dynamic_content_props(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
</script>

<template>
  <Card :title="product.title" :description="product.description">
    Content
  </Card>
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
