<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Frontend;

use JesseGall\CodeCommandments\Prophets\Frontend\KebabCasePropsProphet;
use JesseGall\CodeCommandments\Tests\TestCase;

class KebabCasePropsProphetTest extends TestCase
{
    private KebabCasePropsProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new KebabCasePropsProphet();
    }

    public function test_detects_camel_case_prop_bindings(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
const data = ref('test');
</script>

<template>
    <MyComponent :someValue="data" />
</template>
VUE;

        $judgment = $this->prophet->judge('/components/Test.vue', $content);

        $this->assertTrue($judgment->isFallen());
        $this->assertEquals(1, $judgment->sinCount());
    }

    public function test_detects_multiple_camel_case_bindings(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
const data = ref('test');
</script>

<template>
    <MyComponent
        :firstName="first"
        :lastName="last"
        :userEmail="email"
    />
</template>
VUE;

        $judgment = $this->prophet->judge('/components/Test.vue', $content);

        $this->assertTrue($judgment->isFallen());
        $this->assertEquals(3, $judgment->sinCount());
    }

    public function test_detects_v_bind_camel_case(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
const data = ref('test');
</script>

<template>
    <MyComponent v-bind:someValue="data" />
</template>
VUE;

        $judgment = $this->prophet->judge('/components/Test.vue', $content);

        $this->assertTrue($judgment->isFallen());
        $this->assertEquals(1, $judgment->sinCount());
    }

    public function test_passes_kebab_case_bindings(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
const data = ref('test');
</script>

<template>
    <MyComponent :some-value="data" />
</template>
VUE;

        $judgment = $this->prophet->judge('/components/Test.vue', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_passes_single_word_bindings(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
const data = ref('test');
</script>

<template>
    <MyComponent :value="data" :name="name" />
</template>
VUE;

        $judgment = $this->prophet->judge('/components/Test.vue', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_passes_non_bound_attributes(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
</script>

<template>
    <MyComponent someValue="static" />
</template>
VUE;

        $judgment = $this->prophet->judge('/components/Test.vue', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_skips_non_vue_files(): void
    {
        $judgment = $this->prophet->judge('/test/script.ts', 'const x = 1');

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_provides_helpful_suggestion(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
</script>

<template>
    <MyComponent :userName="name" />
</template>
VUE;

        $judgment = $this->prophet->judge('/components/Test.vue', $content);

        $this->assertTrue($judgment->isFallen());
        $this->assertStringContainsString(':user-name=', $judgment->sins[0]->suggestion);
    }

    public function test_handles_multiple_uppercase_letters(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
</script>

<template>
    <MyComponent :userFirstName="name" />
</template>
VUE;

        $judgment = $this->prophet->judge('/components/Test.vue', $content);

        $this->assertTrue($judgment->isFallen());
        $this->assertStringContainsString(':user-first-name=', $judgment->sins[0]->suggestion);
    }

    public function test_provides_helpful_description(): void
    {
        $this->assertNotEmpty($this->prophet->description());
        $this->assertNotEmpty($this->prophet->detailedDescription());
    }

    public function test_can_repent_vue_files(): void
    {
        $this->assertTrue($this->prophet->canRepent('/components/Test.vue'));
        $this->assertFalse($this->prophet->canRepent('/components/Test.ts'));
    }

    public function test_repent_converts_camel_case_to_kebab_case(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
const data = ref('test');
</script>

<template>
    <MyComponent :someValue="data" />
</template>
VUE;

        $result = $this->prophet->repent('/components/Test.vue', $content);

        $this->assertTrue($result->absolved);
        $this->assertStringContainsString(':some-value="data"', $result->newContent);
        $this->assertStringNotContainsString(':someValue="data"', $result->newContent);
    }

    public function test_repent_converts_multiple_bindings(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
</script>

<template>
    <MyComponent
        :firstName="first"
        :lastName="last"
        :userEmail="email"
    />
</template>
VUE;

        $result = $this->prophet->repent('/components/Test.vue', $content);

        $this->assertTrue($result->absolved);
        $this->assertStringContainsString(':first-name="first"', $result->newContent);
        $this->assertStringContainsString(':last-name="last"', $result->newContent);
        $this->assertStringContainsString(':user-email="email"', $result->newContent);
        $this->assertCount(3, $result->penance);
    }

    public function test_repent_converts_v_bind_syntax(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
</script>

<template>
    <MyComponent v-bind:someValue="data" />
</template>
VUE;

        $result = $this->prophet->repent('/components/Test.vue', $content);

        $this->assertTrue($result->absolved);
        $this->assertStringContainsString('v-bind:some-value="data"', $result->newContent);
    }

    public function test_repent_unchanged_when_already_kebab_case(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
</script>

<template>
    <MyComponent :some-value="data" />
</template>
VUE;

        $result = $this->prophet->repent('/components/Test.vue', $content);

        $this->assertFalse($result->absolved);
    }

    public function test_repent_preserves_script_and_style_sections(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
const someValue = ref('test');
</script>

<template>
    <MyComponent :someValue="someValue" />
</template>

<style scoped>
.someValue { color: red; }
</style>
VUE;

        $result = $this->prophet->repent('/components/Test.vue', $content);

        $this->assertTrue($result->absolved);
        $this->assertStringContainsString('const someValue = ref', $result->newContent);
        $this->assertStringContainsString('.someValue { color: red; }', $result->newContent);
        $this->assertStringContainsString(':some-value="someValue"', $result->newContent);
    }

    public function test_detects_boolean_binding_camel_case(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
</script>

<template>
    <MyComponent :fullWidth="true" />
</template>
VUE;

        $judgment = $this->prophet->judge('/components/Test.vue', $content);

        $this->assertTrue($judgment->isFallen());
        $this->assertStringContainsString(':full-width=', $judgment->sins[0]->suggestion);
    }

    public function test_repent_converts_boolean_binding(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
</script>

<template>
    <MyComponent :fullWidth="true" :isVisible="false" />
</template>
VUE;

        $result = $this->prophet->repent('/components/Test.vue', $content);

        $this->assertTrue($result->absolved);
        $this->assertStringContainsString(':full-width="true"', $result->newContent);
        $this->assertStringContainsString(':is-visible="false"', $result->newContent);
    }
}
