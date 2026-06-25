<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Frontend;

use JesseGall\CodeCommandments\Prophets\Frontend\ScriptFirstProphet;
use JesseGall\CodeCommandments\Tests\TestCase;

class ScriptFirstProphetTest extends TestCase
{
    private ScriptFirstProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new ScriptFirstProphet();
    }

    public function test_detects_template_before_script(): void
    {
        $content = <<<'VUE'
<template>
    <div>Content</div>
</template>

<script setup lang="ts">
const message = 'Hello'
</script>
VUE;

        $judgment = $this->prophet->judge('/test.vue', $content);

        $this->assertTrue($judgment->isFallen());
    }

    public function test_passes_script_first(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
const message = 'Hello'
</script>

<template>
    <div>{{ message }}</div>
</template>
VUE;

        $judgment = $this->prophet->judge('/test.vue', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_skips_non_vue_files(): void
    {
        $content = '<template><div></div></template>';

        $judgment = $this->prophet->judge('/test.html', $content);

        $this->assertTrue($judgment->isRighteous());
    }
}
