<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Frontend;

use JesseGall\CodeCommandments\Prophets\Frontend\InlineMarkupProphet;
use JesseGall\CodeCommandments\Tests\TestCase;

class InlineMarkupProphetTest extends TestCase
{
    private InlineMarkupProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new InlineMarkupProphet();
    }

    public function test_detects_too_many_html_tags(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
</script>

<template>
    <div class="page">
        <div class="header">
            <h1>Title</h1>
            <span class="badge">Status</span>
            <p>Subtitle</p>
        </div>
        <div class="body">
            <div class="section">
                <h2>Section</h2>
                <p>Description</p>
                <ul>
                    <li>Item 1</li>
                    <li>Item 2</li>
                    <li>Item 3</li>
                </ul>
            </div>
            <div class="footer">
                <button>Submit</button>
                <a href="#">Cancel</a>
            </div>
        </div>
    </div>
</template>
VUE;

        $judgment = $this->prophet->judge('/test/Component.vue', $content);

        $this->assertTrue($judgment->hasWarnings());
    }

    public function test_passes_with_few_html_tags(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
</script>

<template>
    <ProductPage>
        <ProductHeader :product="product" />
        <ProductDetails :product="product" />
        <div class="actions">
            <ActionButton label="Save" />
        </div>
    </ProductPage>
</template>
VUE;

        $judgment = $this->prophet->judge('/test/Component.vue', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_excludes_template_and_slot_tags(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
</script>

<template>
    <div>
        <template v-if="show">
            <slot name="header" />
            <template v-for="item in items" :key="item.id">
                <slot :item="item" />
            </template>
        </template>
    </div>
</template>
VUE;

        $judgment = $this->prophet->judge('/test/Component.vue', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_count_component_tags(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
</script>

<template>
    <PageLayout>
        <HeaderBar />
        <NavigationMenu />
        <ContentArea>
            <ProductCard />
            <ProductCard />
            <ProductCard />
        </ContentArea>
        <SidePanel>
            <FilterList />
            <SortOptions />
        </SidePanel>
        <FooterBar />
        <ModalDialog />
        <ToastNotification />
        <LoadingSpinner />
        <ErrorBoundary />
        <BreadcrumbTrail />
        <PaginationControls />
    </PageLayout>
</template>
VUE;

        $judgment = $this->prophet->judge('/test/Component.vue', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_respects_configurable_threshold(): void
    {
        $this->prophet->configure(['max_html_tags' => 3]);

        $content = <<<'VUE'
<script setup lang="ts">
</script>

<template>
    <div>
        <h1>Title</h1>
        <p>First</p>
        <p>Second</p>
    </div>
</template>
VUE;

        $judgment = $this->prophet->judge('/test/Component.vue', $content);

        $this->assertTrue($judgment->hasWarnings());
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
