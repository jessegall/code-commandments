<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Frontend;

use JesseGall\CodeCommandments\Prophets\Frontend\PropsTypeScriptProphet;
use JesseGall\CodeCommandments\Tests\TestCase;

class PropsTypeScriptProphetTest extends TestCase
{
    private PropsTypeScriptProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new PropsTypeScriptProphet();
    }

    public function test_detects_runtime_object_props(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
const props = defineProps({
    title: String,
    count: { type: Number, default: 0 }
});
</script>

<template>
  <div>{{ title }}</div>
</template>
VUE;

        $judgment = $this->prophet->judge('/test/Component.vue', $content);

        $this->assertTrue($judgment->isFallen());
        $this->assertGreaterThan(0, $judgment->sinCount());
    }

    public function test_passes_typescript_generic_props(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
interface Props {
    title: string;
    count?: number;
}

const props = withDefaults(defineProps<Props>(), {
    count: 0
});
</script>

<template>
  <div>{{ title }}</div>
</template>
VUE;

        $judgment = $this->prophet->judge('/test/Component.vue', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_passes_inline_typescript_props(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
const props = defineProps<{
    title: string;
    count?: number;
}>();
</script>

<template>
  <div>{{ title }}</div>
</template>
VUE;

        $judgment = $this->prophet->judge('/test/Component.vue', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_passes_no_props(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
const count = ref(0);
</script>

<template>
  <div>{{ count }}</div>
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
