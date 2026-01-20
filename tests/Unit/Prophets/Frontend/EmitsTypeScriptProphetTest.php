<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Frontend;

use JesseGall\CodeCommandments\Prophets\Frontend\EmitsTypeScriptProphet;
use JesseGall\CodeCommandments\Tests\TestCase;

class EmitsTypeScriptProphetTest extends TestCase
{
    private EmitsTypeScriptProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new EmitsTypeScriptProphet();
    }

    public function test_detects_runtime_array_emits(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
const emit = defineEmits(['update', 'close']);
</script>

<template>
  <div>Test</div>
</template>
VUE;

        $judgment = $this->prophet->judge('/test/Component.vue', $content);

        $this->assertTrue($judgment->isFallen());
        $this->assertGreaterThan(0, $judgment->sinCount());
    }

    public function test_passes_typescript_generic_emits(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
interface Emits {
    (e: 'update', value: string): void;
    (e: 'close'): void;
}

const emit = defineEmits<Emits>();
</script>

<template>
  <div>Test</div>
</template>
VUE;

        $judgment = $this->prophet->judge('/test/Component.vue', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_passes_inline_typescript_emits(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
const emit = defineEmits<{
    (e: 'update', value: string): void;
    (e: 'close'): void;
}>();
</script>

<template>
  <div>Test</div>
</template>
VUE;

        $judgment = $this->prophet->judge('/test/Component.vue', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_passes_no_emits(): void
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
