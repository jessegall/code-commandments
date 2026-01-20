<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Frontend;

use JesseGall\CodeCommandments\Prophets\Frontend\PageDataAccessProphet;
use JesseGall\CodeCommandments\Tests\TestCase;

class PageDataAccessProphetTest extends TestCase
{
    private PageDataAccessProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new PageDataAccessProphet();
    }

    public function test_detects_direct_data_types_in_pages(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
interface Props {
    products: ProductData[];
    user: UserData;
}
</script>

<template>
  <div>Test</div>
</template>
VUE;

        $judgment = $this->prophet->judge('/pages/Products/Index.vue', $content);

        $this->assertTrue($judgment->isFallen());
        $this->assertGreaterThan(0, $judgment->sinCount());
    }

    public function test_detects_direct_data_suffix_types(): void
    {
        // The prophet specifically checks for types ending with "Data" suffix
        // (like ProductData, UserData) with a semicolon
        $content = <<<'VUE'
<script setup lang="ts">
interface Props {
    item: ItemData;
}
</script>

<template>
  <div>Test</div>
</template>
VUE;

        $judgment = $this->prophet->judge('/Pages/Products/Index.vue', $content);

        $this->assertTrue($judgment->isFallen());
    }

    public function test_passes_page_data_indexed_access(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
type ProductsIndexPage = App.Http.View.Products.ProductsIndexPage;
interface Props {
    products: ProductsIndexPage['products'];
    user: ProductsIndexPage['user'];
}
</script>

<template>
  <div>Test</div>
</template>
VUE;

        $judgment = $this->prophet->judge('/pages/Products/Index.vue', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_skips_non_page_files(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
interface Props {
    products: ProductData[];
}
</script>

<template>
  <div>Test</div>
</template>
VUE;

        $judgment = $this->prophet->judge('/components/ProductList.vue', $content);

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
