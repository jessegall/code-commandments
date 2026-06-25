<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Frontend;

use JesseGall\CodeCommandments\Prophets\Frontend\ArrowFunctionAssignmentsProphet;
use JesseGall\CodeCommandments\Tests\TestCase;

class ArrowFunctionAssignmentsProphetTest extends TestCase
{
    private ArrowFunctionAssignmentsProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new ArrowFunctionAssignmentsProphet();
    }

    public function test_detects_arrow_function_assignment(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
const handleClick = () => {
    console.log('clicked');
};
</script>

<template>
  <button @click="handleClick">Click</button>
</template>
VUE;

        $judgment = $this->prophet->judge('/test/Component.vue', $content);

        $this->assertTrue($judgment->isFallen());
        $this->assertGreaterThan(0, $judgment->sinCount());
    }

    public function test_detects_async_arrow_function_assignment(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
const fetchData = async () => {
    await api.get('/data');
};
</script>

<template>
  <div>Test</div>
</template>
VUE;

        $judgment = $this->prophet->judge('/test/Component.vue', $content);

        $this->assertTrue($judgment->isFallen());
    }

    public function test_passes_named_function_declaration(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
function handleClick() {
    console.log('clicked');
}

async function fetchData() {
    await api.get('/data');
}
</script>

<template>
  <button @click="handleClick">Click</button>
</template>
VUE;

        $judgment = $this->prophet->judge('/test/Component.vue', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_allows_inline_arrow_functions(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
const items = data.map(item => item.id);
const double = (n: number) => n * 2;
</script>

<template>
  <div>Test</div>
</template>
VUE;

        $judgment = $this->prophet->judge('/test/Component.vue', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_allows_computed_with_arrow_function(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
import { computed } from 'vue';

const fullName = computed(() => {
    return firstName.value + ' ' + lastName.value;
});
</script>

<template>
  <div>{{ fullName }}</div>
</template>
VUE;

        $judgment = $this->prophet->judge('/test/Component.vue', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_skips_non_vue_files(): void
    {
        $judgment = $this->prophet->judge('/test/script.ts', 'const x = () => { return 1; }');

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_provides_helpful_description(): void
    {
        $this->assertNotEmpty($this->prophet->description());
        $this->assertNotEmpty($this->prophet->detailedDescription());
    }
}
