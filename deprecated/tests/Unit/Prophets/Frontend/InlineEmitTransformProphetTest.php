<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Frontend;

use JesseGall\CodeCommandments\Prophets\Frontend\InlineEmitTransformProphet;
use JesseGall\CodeCommandments\Tests\TestCase;

class InlineEmitTransformProphetTest extends TestCase
{
    private InlineEmitTransformProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new InlineEmitTransformProphet();
    }

    public function test_detects_emit_with_or_operator(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
</script>

<template>
  <Button @click="$emit('update', value || defaultValue)">Click</Button>
</template>
VUE;

        $judgment = $this->prophet->judge('/test/Component.vue', $content);

        $this->assertTrue($judgment->isFallen());
        $this->assertGreaterThan(0, $judgment->sinCount());
    }

    public function test_detects_emit_with_and_operator(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
</script>

<template>
  <Button @click="$emit('update', isValid && value)">Click</Button>
</template>
VUE;

        $judgment = $this->prophet->judge('/test/Component.vue', $content);

        $this->assertTrue($judgment->isFallen());
    }

    public function test_detects_emit_with_ternary(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
</script>

<template>
  <Button @click="$emit('update', isActive ? 'yes' : 'no')">Click</Button>
</template>
VUE;

        $judgment = $this->prophet->judge('/test/Component.vue', $content);

        $this->assertTrue($judgment->isFallen());
    }

    public function test_passes_simple_emit(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
</script>

<template>
  <Button @click="$emit('update', value)">Click</Button>
</template>
VUE;

        $judgment = $this->prophet->judge('/test/Component.vue', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_passes_emit_with_function_call(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
</script>

<template>
  <Button @click="handleClick">Click</Button>
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
