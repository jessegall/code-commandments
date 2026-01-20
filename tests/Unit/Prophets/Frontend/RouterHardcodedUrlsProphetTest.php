<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Frontend;

use JesseGall\CodeCommandments\Prophets\Frontend\RouterHardcodedUrlsProphet;
use JesseGall\CodeCommandments\Tests\TestCase;

class RouterHardcodedUrlsProphetTest extends TestCase
{
    private RouterHardcodedUrlsProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new RouterHardcodedUrlsProphet();
    }

    public function test_detects_hardcoded_url_in_router_visit(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
router.visit('/products');
</script>

<template>
  <div>Test</div>
</template>
VUE;

        $judgment = $this->prophet->judge('/test/Component.vue', $content);

        $this->assertTrue($judgment->isFallen());
        $this->assertGreaterThan(0, $judgment->sinCount());
    }

    public function test_detects_hardcoded_url_in_router_push(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
router.push('/orders/123');
</script>

<template>
  <div>Test</div>
</template>
VUE;

        $judgment = $this->prophet->judge('/test/Component.vue', $content);

        $this->assertTrue($judgment->isFallen());
    }

    public function test_detects_hardcoded_url_in_router_replace(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
router.replace('/dashboard');
</script>

<template>
  <div>Test</div>
</template>
VUE;

        $judgment = $this->prophet->judge('/test/Component.vue', $content);

        $this->assertTrue($judgment->isFallen());
    }

    public function test_passes_wayfinder_routes(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
router.visit(products.index.url());
router.push(orders.show.url(orderId));
</script>

<template>
  <div>Test</div>
</template>
VUE;

        $judgment = $this->prophet->judge('/test/Component.vue', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_skips_routes_directory(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
router.visit('/products');
</script>

<template>
  <div>Test</div>
</template>
VUE;

        $judgment = $this->prophet->judge('/routes/index.vue', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_skips_actions_directory(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
router.visit('/products');
</script>

<template>
  <div>Test</div>
</template>
VUE;

        $judgment = $this->prophet->judge('/actions/redirect.vue', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_works_with_ts_files(): void
    {
        $content = <<<'TS'
router.visit('/products');
TS;

        $judgment = $this->prophet->judge('/test/script.ts', $content);

        $this->assertTrue($judgment->isFallen());
    }

    public function test_skips_non_applicable_files(): void
    {
        $judgment = $this->prophet->judge('/test/script.php', 'router.visit("/products")');

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_provides_helpful_description(): void
    {
        $this->assertNotEmpty($this->prophet->description());
        $this->assertNotEmpty($this->prophet->detailedDescription());
    }
}
