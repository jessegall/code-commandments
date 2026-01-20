<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Frontend;

use JesseGall\CodeCommandments\Prophets\Frontend\ConditionalArrayBuildingProphet;
use JesseGall\CodeCommandments\Tests\TestCase;

class ConditionalArrayBuildingProphetTest extends TestCase
{
    private ConditionalArrayBuildingProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new ConditionalArrayBuildingProphet();
    }

    public function test_detects_conditional_spread_pattern(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
const actions = [
    ...(canEdit ? [{ label: 'Edit' }] : []),
    ...(canDelete ? [{ label: 'Delete' }] : []),
];
</script>

<template>
  <div>Test</div>
</template>
VUE;

        $judgment = $this->prophet->judge('/test/Component.vue', $content);

        $this->assertTrue($judgment->isFallen());
        $this->assertGreaterThan(0, $judgment->sinCount());
    }

    public function test_detects_array_push_usage(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
const actions = [];
if (canEdit) actions.push({ label: 'Edit' });
if (canDelete) actions.push({ label: 'Delete' });
</script>

<template>
  <div>Test</div>
</template>
VUE;

        $judgment = $this->prophet->judge('/test/Component.vue', $content);

        $this->assertTrue($judgment->isFallen());
    }

    public function test_passes_disabled_flags_pattern(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
const actions = [
    { label: 'Edit', disabled: !canEdit },
    { label: 'Delete', disabled: !canDelete },
].filter(a => !a.disabled);
</script>

<template>
  <div>Test</div>
</template>
VUE;

        $judgment = $this->prophet->judge('/test/Component.vue', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_passes_show_property_pattern(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
const actions = [
    { label: 'Edit', show: canEdit },
    { label: 'Delete', show: canDelete },
];
</script>

<template>
  <div>Test</div>
</template>
VUE;

        $judgment = $this->prophet->judge('/test/Component.vue', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_skips_non_vue_files(): void
    {
        $judgment = $this->prophet->judge('/test/script.ts', 'arr.push(1)');

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_provides_helpful_description(): void
    {
        $this->assertNotEmpty($this->prophet->description());
        $this->assertNotEmpty($this->prophet->detailedDescription());
    }
}
