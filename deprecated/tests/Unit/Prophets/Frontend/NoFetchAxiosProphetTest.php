<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Frontend;

use JesseGall\CodeCommandments\Prophets\Frontend\NoFetchAxiosProphet;
use JesseGall\CodeCommandments\Tests\TestCase;

class NoFetchAxiosProphetTest extends TestCase
{
    private NoFetchAxiosProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new NoFetchAxiosProphet();
    }

    public function test_detects_fetch_usage(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
async function loadData() {
    const response = await fetch('/api/users')
    return response.json()
}
</script>

<template>
    <div>Content</div>
</template>
VUE;

        $judgment = $this->prophet->judge('/test.vue', $content);

        $this->assertTrue($judgment->isFallen());
    }

    public function test_detects_axios_usage(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
import axios from 'axios'

async function loadData() {
    const response = await axios.get('/api/users')
    return response.data
}
</script>

<template>
    <div>Content</div>
</template>
VUE;

        $judgment = $this->prophet->judge('/test.vue', $content);

        // Prophet detects both fetch AND axios (use centralized API client)
        $this->assertTrue($judgment->isFallen());
    }

    public function test_passes_inertia_router(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
import { router } from '@inertiajs/vue3'

function loadData() {
    router.visit('/users')
}
</script>

<template>
    <div>Content</div>
</template>
VUE;

        $judgment = $this->prophet->judge('/test.vue', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_skips_non_vue_files(): void
    {
        $content = 'fetch("/api/data")';

        $judgment = $this->prophet->judge('/test.html', $content);

        $this->assertTrue($judgment->isRighteous());
    }
}
