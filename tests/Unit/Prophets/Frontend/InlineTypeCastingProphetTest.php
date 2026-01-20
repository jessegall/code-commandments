<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Frontend;

use JesseGall\CodeCommandments\Prophets\Frontend\InlineTypeCastingProphet;
use JesseGall\CodeCommandments\Tests\TestCase;

class InlineTypeCastingProphetTest extends TestCase
{
    private InlineTypeCastingProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new InlineTypeCastingProphet();
    }

    public function test_detects_type_cast_in_prop(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
</script>

<template>
  <Component :items="data as ItemData" />
</template>
VUE;

        $judgment = $this->prophet->judge('/test/Component.vue', $content);

        $this->assertTrue($judgment->hasWarnings());
    }

    public function test_detects_type_assertion_in_prop(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
</script>

<template>
  <Component :user="user as UserData" />
</template>
VUE;

        $judgment = $this->prophet->judge('/test/Component.vue', $content);

        $this->assertTrue($judgment->hasWarnings());
    }

    public function test_passes_as_const(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
</script>

<template>
  <Component :value="'fixed' as const" />
</template>
VUE;

        $judgment = $this->prophet->judge('/test/Component.vue', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_passes_no_type_casting(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
</script>

<template>
  <Component :items="data" :user="user" />
</template>
VUE;

        $judgment = $this->prophet->judge('/test/Component.vue', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_passes_as_in_text_content(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
</script>

<template>
  <div>Use this component as a button</div>
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
