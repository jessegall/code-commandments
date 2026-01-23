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

    public function test_detects_appearance_class_override_on_item_card(): void
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

    public function test_detects_width_class_override_on_button(): void
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

    public function test_detects_size_class_override(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
</script>

<template>
  <Button class="text-sm">Submit</Button>
</template>
VUE;

        $judgment = $this->prophet->judge('/test/Component.vue', $content);

        $this->assertTrue($judgment->isFallen());
    }

    public function test_detects_content_class_with_appearance_classes(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
</script>

<template>
  <Card content-class="bg-gray-100">Content</Card>
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

    public function test_passes_margin_classes(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
</script>

<template>
  <Button class="mt-4 mb-2 mx-auto">Submit</Button>
</template>
VUE;

        $judgment = $this->prophet->judge('/test/Component.vue', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_passes_grid_layout_classes(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
</script>

<template>
  <div class="grid grid-cols-3">
    <Button class="col-span-2">Submit</Button>
  </div>
</template>
VUE;

        $judgment = $this->prophet->judge('/test/Component.vue', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_passes_flex_behavior_classes(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
</script>

<template>
  <div class="flex">
    <Input class="flex-1" />
    <Button class="grow-0 shrink">Submit</Button>
  </div>
</template>
VUE;

        $judgment = $this->prophet->judge('/test/Component.vue', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_passes_positioning_classes(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
</script>

<template>
  <Card class="absolute top-0 right-0 z-10">Content</Card>
</template>
VUE;

        $judgment = $this->prophet->judge('/test/Component.vue', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_passes_display_classes(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
</script>

<template>
  <Button class="hidden">Hidden Button</Button>
  <Badge class="inline-block">Tag</Badge>
</template>
VUE;

        $judgment = $this->prophet->judge('/test/Component.vue', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_passes_arbitrary_width_height(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
</script>

<template>
  <Card class="w-[200px] min-h-[100px]">Content</Card>
</template>
VUE;

        $judgment = $this->prophet->judge('/test/Component.vue', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_detects_mixed_allowed_and_disallowed_classes(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
</script>

<template>
  <Button class="mt-4 bg-blue-500 col-span-2">Submit</Button>
</template>
VUE;

        $judgment = $this->prophet->judge('/test/Component.vue', $content);

        $this->assertTrue($judgment->isFallen());
        // Should only report the disallowed class
        $this->assertStringContainsString('bg-blue-500', $judgment->sins[0]->message);
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
  <MyCustomComponent class="bg-red-500">Content</MyCustomComponent>
</template>
VUE;

        $judgment = $this->prophet->judge('/test/Component.vue', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_passes_negative_margin_classes(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
</script>

<template>
  <Button class="-mt-2 -ml-4">Submit</Button>
</template>
VUE;

        $judgment = $this->prophet->judge('/test/Component.vue', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_passes_self_alignment_classes(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
</script>

<template>
  <Button class="self-center justify-self-end">Submit</Button>
</template>
VUE;

        $judgment = $this->prophet->judge('/test/Component.vue', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_ignores_dynamic_class_bindings(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
const dynamicClasses = 'bg-red-500 text-white';
</script>

<template>
  <Button :class="dynamicClasses">Submit</Button>
  <Card :class="{ 'bg-blue-500': isActive }">Content</Card>
</template>
VUE;

        $judgment = $this->prophet->judge('/test/Component.vue', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_detects_static_class_but_ignores_dynamic(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
</script>

<template>
  <Button class="bg-red-500" :class="extraClasses">Submit</Button>
</template>
VUE;

        $judgment = $this->prophet->judge('/test/Component.vue', $content);

        // Should flag bg-red-500 from static class, ignore dynamic binding
        $this->assertTrue($judgment->isFallen());
        $this->assertStringContainsString('bg-red-500', $judgment->sins[0]->message);
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
