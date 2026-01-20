<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Frontend;

use JesseGall\CodeCommandments\Prophets\Frontend\StyleOverridesProphet;
use JesseGall\CodeCommandments\Tests\TestCase;

class StyleOverridesProphetTest extends TestCase
{
    private StyleOverridesProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new StyleOverridesProphet();
    }

    public function test_detects_class_override_on_item_card(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
</script>

<template>
  <ItemCard class="bg-red-100 border-red-500">
    Content
  </ItemCard>
</template>
VUE;

        $judgment = $this->prophet->judge('/test/Component.vue', $content);

        $this->assertTrue($judgment->isFallen());
        $this->assertGreaterThan(0, $judgment->sinCount());
    }

    public function test_detects_class_override_on_button(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
</script>

<template>
  <Button class="w-full">Submit</Button>
</template>
VUE;

        $judgment = $this->prophet->judge('/test/Component.vue', $content);

        $this->assertTrue($judgment->isFallen());
    }

    public function test_detects_content_class_attribute(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
</script>

<template>
  <Card content-class="p-4 bg-gray-100">Content</Card>
</template>
VUE;

        $judgment = $this->prophet->judge('/test/Component.vue', $content);

        $this->assertTrue($judgment->isFallen());
    }

    public function test_passes_semantic_props(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
</script>

<template>
  <ItemCard variant="danger">Content</ItemCard>
  <Button fullWidth>Submit</Button>
</template>
VUE;

        $judgment = $this->prophet->judge('/test/Component.vue', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_skips_component_definition_file(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
</script>

<template>
  <div class="bg-red-100">Content</div>
</template>
VUE;

        $judgment = $this->prophet->judge('/components/ItemCard.vue', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_passes_class_on_non_base_components(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
</script>

<template>
  <MyCustomComponent class="mt-4">Content</MyCustomComponent>
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
