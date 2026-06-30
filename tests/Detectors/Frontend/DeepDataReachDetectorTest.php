<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Frontend;

use JesseGall\CodeCommandments\Detectors\Frontend\DeepDataReachDetector;
use JesseGall\CodeCommandments\Vue\Codebase;
use PHPUnit\Framework\TestCase;

final class DeepDataReachDetectorTest extends TestCase
{
    public function test_flags_a_cluster_of_reaches_into_one_object(): void
    {
        // order.customer read in two fields → a cluster worth extracting (F1).
        $found = $this->findBig('<section><p>{{ order.customer.fullName }}</p><p>{{ order.customer.email }}</p></section>');

        $this->assertCount(1, $found);
    }

    public function test_climbs_to_the_lowest_common_ancestor(): void
    {
        // The reaches sit in two different branches; the finding is their shared
        // ancestor — the element to extract — not either leaf (F2).
        $found = $this->findBig(
            '<section class="boundary"><div class="a"><p>{{ order.customer.fullName }}</p></div>'
            . '<div class="b"><p>{{ order.customer.email }}</p></div></section>',
        );

        $this->assertCount(1, $found);
        $this->assertSame('section', $found[0]->tag);
        $this->assertSame('boundary', $found[0]->attribute('class'));
    }

    public function test_ignores_a_lone_deep_reach(): void
    {
        // One field off order.customer is a single reach, not a cluster (F1).
        $this->assertCount(0, $this->findBig('<span>{{ order.customer.fullName }}</span>'));
    }

    public function test_ignores_a_reactive_root(): void
    {
        // `form` is v-model-bound → owned state, not a domain object: reaching
        // form.errors.* deeply is no sin, however many fields (R1).
        $found = $this->findBig(
            '<form><input v-model="form.data.name" />'
            . '<p>{{ form.errors.name }}</p><p>{{ form.errors.email }}</p></form>',
        );

        $this->assertCount(0, $found);
    }

    public function test_ignores_a_cluster_in_a_small_template(): void
    {
        $found = new DeepDataReachDetector()->find(Codebase::fromString(
            '<template><section><p>{{ order.customer.fullName }}</p><p>{{ order.customer.email }}</p></section></template>',
        ));

        $this->assertCount(0, $found);
    }

    public function test_ignores_shallow_access(): void
    {
        $this->assertCount(0, $this->findBig('<span>{{ order.total }}</span><span>{{ order.tax }}</span>'));
    }

    public function test_transparent_accessors_do_not_deepen_a_reach(): void
    {
        // box.cfg.value strips to box.cfg (a count/ref unwrap, not nesting), leaving
        // only one genuine deep field off box.cfg — so no cluster.
        $this->assertCount(0, $this->findBig('<p>{{ box.cfg.value }}</p><p>{{ box.cfg.size }}</p>'));
    }

    public function test_ignores_method_calls_and_dotted_string_literals(): void
    {
        // `order.customer.greet()` reaches the DATA order.customer (depth 1), and
        // `route('a.b.c')` is a string literal — neither is a deep field.
        $this->assertCount(0, $this->findBig(
            '<RouterLink :to="route(\'admin.orders.show\')">{{ order.customer.greet() }}</RouterLink>'
            . '<span>{{ order.customer.wave() }}</span>',
        ));
    }

    /**
     * @return list<\JesseGall\CodeCommandments\Vue\ElementMatch>
     */
    private function findBig(string $body): array
    {
        $filler = str_repeat("  <p>row</p>\n", 55);

        return new DeepDataReachDetector()->find(
            Codebase::fromString("<template>\n  <div>\n{$filler}  {$body}\n  </div>\n</template>"),
        );
    }
}
