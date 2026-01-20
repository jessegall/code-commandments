<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Frontend;

use JesseGall\CodeCommandments\Prophets\Frontend\InlineDialogProphet;
use JesseGall\CodeCommandments\Tests\TestCase;

class InlineDialogProphetTest extends TestCase
{
    private InlineDialogProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new InlineDialogProphet();
    }

    public function test_detects_long_inline_dialog_in_pages(): void
    {
        // Create a dialog with content over 200 chars
        $longContent = str_repeat('Long content that makes this dialog too big. ', 10);
        $content = <<<VUE
<script setup lang="ts">
</script>

<template>
  <div>
    <Dialog>
      <DialogContent>
        {$longContent}
      </DialogContent>
    </Dialog>
  </div>
</template>
VUE;

        $judgment = $this->prophet->judge('/Pages/Products/Index.vue', $content);

        $this->assertTrue($judgment->hasWarnings());
    }

    public function test_passes_short_dialogs(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
</script>

<template>
  <Dialog>
    <DialogContent>
      Short content
    </DialogContent>
  </Dialog>
</template>
VUE;

        $judgment = $this->prophet->judge('/Pages/Products/Index.vue', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_passes_dialog_components_extracted(): void
    {
        $content = <<<'VUE'
<script setup lang="ts">
</script>

<template>
  <ProductDialog :product="selectedProduct" @close="closeDialog" />
</template>
VUE;

        $judgment = $this->prophet->judge('/Pages/Products/Index.vue', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_skips_non_page_files(): void
    {
        $longContent = str_repeat('Long content that makes this dialog too big. ', 10);
        $content = <<<VUE
<script setup lang="ts">
</script>

<template>
  <Dialog>
    <DialogContent>
      {$longContent}
    </DialogContent>
  </Dialog>
</template>
VUE;

        $judgment = $this->prophet->judge('/components/MyComponent.vue', $content);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_requires_confession(): void
    {
        $this->assertTrue($this->prophet->requiresConfession());
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
