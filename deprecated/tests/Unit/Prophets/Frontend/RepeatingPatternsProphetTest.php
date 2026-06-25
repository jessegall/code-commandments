<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Frontend;

use JesseGall\CodeCommandments\Prophets\Frontend\RepeatingPatternsProphet;
use JesseGall\CodeCommandments\Tests\TestCase;

class RepeatingPatternsProphetTest extends TestCase
{
    private RepeatingPatternsProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new RepeatingPatternsProphet();
    }

    public function test_detects_multiple_dialogs(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
</script>

<template>
  <Dialog>First</Dialog>
  <Dialog>Second</Dialog>
  <Dialog>Third</Dialog>
</template>
VUE;

        $judgment = $this->prophet->judge('/test/Component.vue', $content);

        $this->assertTrue($judgment->hasWarnings());
    }

    public function test_detects_multiple_dialog_state_refs(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
const createDialogOpen = ref(false);
const editDialogOpen = ref(false);
const deleteDialogOpen = ref(false);
const viewDialogOpen = ref(false);
</script>

<template>
  <div>Test</div>
</template>
VUE;

        $judgment = $this->prophet->judge('/test/Component.vue', $content);

        $this->assertTrue($judgment->hasWarnings());
    }

    public function test_detects_multiple_open_functions(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
function openCreateDialog() {}
function openEditDialog() {}
function openDeleteDialog() {}
function openViewDialog() {}
</script>

<template>
  <div>Test</div>
</template>
VUE;

        $judgment = $this->prophet->judge('/test/Component.vue', $content);

        $this->assertTrue($judgment->hasWarnings());
    }

    public function test_detects_many_form_field_bindings(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
</script>

<template>
  <Input v-model="form.name" />
  <Input v-model="form.email" />
  <Input v-model="form.phone" />
  <Input v-model="form.address" />
  <Input v-model="form.city" />
  <Input v-model="form.state" />
</template>
VUE;

        $judgment = $this->prophet->judge('/test/Component.vue', $content);

        $this->assertTrue($judgment->hasWarnings());
    }

    public function test_passes_simple_component(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
const isOpen = ref(false);

function openDialog() {
    isOpen.value = true;
}
</script>

<template>
  <Dialog>Content</Dialog>
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
