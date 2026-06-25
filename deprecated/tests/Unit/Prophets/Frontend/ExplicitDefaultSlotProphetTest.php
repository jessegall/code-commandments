<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Frontend;

use JesseGall\CodeCommandments\Prophets\Frontend\ExplicitDefaultSlotProphet;
use JesseGall\CodeCommandments\Tests\TestCase;

class ExplicitDefaultSlotProphetTest extends TestCase
{
    private ExplicitDefaultSlotProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new ExplicitDefaultSlotProphet();
    }

    public function test_detects_missing_default_slot_with_named_slots(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
</script>

<template>
  <Card>
    <template #header>Header</template>
    Content without explicit default slot
    <template #footer>Footer</template>
  </Card>
</template>
VUE;

        $judgment = $this->prophet->judge('/test/Component.vue', $content);

        $this->assertTrue($judgment->hasWarnings());
    }

    public function test_passes_explicit_default_slot(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
</script>

<template>
  <Card>
    <template #header>Header</template>
    <template #default>Content in explicit default slot</template>
    <template #footer>Footer</template>
  </Card>
</template>
VUE;

        $judgment = $this->prophet->judge('/test/Component.vue', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_passes_only_default_slot(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
</script>

<template>
  <Card>
    Simple content without named slots
  </Card>
</template>
VUE;

        $judgment = $this->prophet->judge('/test/Component.vue', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_passes_only_named_slots_no_implicit_content(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
</script>

<template>
  <Card>
    <template #header>Header</template>
    <template #footer>Footer</template>
  </Card>
</template>
VUE;

        $judgment = $this->prophet->judge('/test/Component.vue', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_a_single_named_slot_with_inner_content(): void
    {
        // Issue #21: a single named slot whose own content has text/elements,
        // with no default content beside it. The text is INSIDE the slot.
        $content = <<<'VUE'
        <template>
          <WorkflowNodeInspector>
            <template #trigger-picker="slotProps">
              <TriggerPicker>Pick a trigger</TriggerPicker>
            </template>
          </WorkflowNodeInspector>
        </template>
        VUE;

        $this->assertTrue($this->prophet->judge('/test/Component.vue', $content)->isRighteous());
    }

    public function test_does_not_flag_multiple_named_slots_with_inner_content(): void
    {
        // Issue #21: only named slots (#interval/#daily/#cron), each with its own
        // inner content, no default content beside them.
        $content = <<<'VUE'
        <template>
          <SwitchCase :value="mode">
            <template #interval><IntervalEditor>every</IntervalEditor></template>
            <template #daily><DailyEditor>daily</DailyEditor></template>
            <template #cron><CronEditor>cron</CronEditor></template>
          </SwitchCase>
        </template>
        VUE;

        $this->assertTrue($this->prophet->judge('/test/Component.vue', $content)->isRighteous());
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
