<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Frontend;

use JesseGall\CodeCommandments\Prophets\Frontend\WayfinderRoutesProphet;
use JesseGall\CodeCommandments\Tests\TestCase;

class WayfinderRoutesProphetTest extends TestCase
{
    private WayfinderRoutesProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new WayfinderRoutesProphet();
    }

    public function test_detects_hardcoded_url_in_dynamic_href_single_quote(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
</script>

<template>
  <Link :href="'/products'">Products</Link>
</template>
VUE;

        $judgment = $this->prophet->judge('/test/Component.vue', $content);

        $this->assertTrue($judgment->isFallen());
        $this->assertGreaterThan(0, $judgment->sinCount());
    }

    public function test_detects_hardcoded_url_with_backtick(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
</script>

<template>
  <Link :href="`/orders/${id}`">View Order</Link>
</template>
VUE;

        $judgment = $this->prophet->judge('/test/Component.vue', $content);

        $this->assertTrue($judgment->isFallen());
    }

    public function test_detects_hardcoded_url_in_dynamic_href(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
</script>

<template>
  <Link :href="'/products/' + id">View</Link>
</template>
VUE;

        $judgment = $this->prophet->judge('/test/Component.vue', $content);

        $this->assertTrue($judgment->isFallen());
    }

    public function test_passes_wayfinder_routes(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
</script>

<template>
  <Link :href="products.index.url()">Products</Link>
  <Link :href="orders.show.url(order.id)">View Order</Link>
</template>
VUE;

        $judgment = $this->prophet->judge('/test/Component.vue', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_passes_external_urls(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
</script>

<template>
  <a href="https://example.com">External</a>
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
