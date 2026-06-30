<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Vue;

use JesseGall\CodeCommandments\Vue\Boundary;
use JesseGall\CodeCommandments\Vue\Codebase;
use JesseGall\CodeCommandments\Vue\ComponentLibrary;
use JesseGall\CodeCommandments\Vue\Element;
use JesseGall\CodeCommandments\Vue\Sfc;
use PHPUnit\Framework\TestCase;

final class ComponentLibraryTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/cc-reuse-' . uniqid();
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob("{$this->dir}/*.vue") ?: []);
        @rmdir($this->dir);
    }

    public function test_reuses_a_component_that_renders_the_same_fields(): void
    {
        $this->write('UserCard.vue', <<<'VUE'
        <script setup lang="ts">defineProps<{ user: User }>();</script>
        <template>
          <div class="card"><h3>{{ user.name }}</h3><p>{{ user.email }}</p></div>
        </template>
        VUE);

        $this->write('OrderScreen.vue', <<<'VUE'
        <script setup lang="ts">defineProps<{ order: Order }>();</script>
        <template>
          <section class="panel">
            <div class="card"><h3>{{ order.customer.name }}</h3><p>{{ order.customer.email }}</p></div>
          </section>
        </template>
        VUE);

        $match = $this->matchCard();

        $this->assertNotNull($match);
        $this->assertSame('UserCard', $match->name);
        $this->assertSame(['user' => 'order.customer'], $match->bindings);
    }

    public function test_does_not_reuse_when_the_displayed_fields_differ(): void
    {
        $this->write('UserCard.vue', <<<'VUE'
        <script setup lang="ts">defineProps<{ user: User }>();</script>
        <template>
          <div class="card"><h3>{{ user.name }}</h3><p>{{ user.email }}</p></div>
        </template>
        VUE);

        // same skeleton, but renders title/sku — not name/email — off the object
        $this->write('OrderScreen.vue', <<<'VUE'
        <script setup lang="ts">defineProps<{ order: Order }>();</script>
        <template>
          <section class="panel">
            <div class="card"><h3>{{ order.product.title }}</h3><p>{{ order.product.sku }}</p></div>
          </section>
        </template>
        VUE);

        $this->assertNull($this->matchCard());
    }

    private function matchCard(): ?\JesseGall\CodeCommandments\Vue\ComponentReuse
    {
        $codebase = Codebase::scan($this->dir);
        $library = ComponentLibrary::from($codebase);

        foreach ($codebase->components() as $sfc) {
            if (! str_ends_with($sfc->path, 'OrderScreen.vue')) {
                continue;
            }

            $card = $this->cardOf($sfc);

            return $library->match(Boundary::at($card, $sfc));
        }

        return null;
    }

    private function cardOf(Sfc $sfc): Element
    {
        foreach ($sfc->template->descendants() as $element) {
            if ($element->attribute('class') === 'card') {
                return $element;
            }
        }

        $this->fail('no .card element');
    }

    private function write(string $name, string $vue): void
    {
        file_put_contents("{$this->dir}/{$name}", $vue);
    }
}
