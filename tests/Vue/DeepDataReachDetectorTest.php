<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Vue;

use JesseGall\CodeCommandments\Detectors\Frontend\DeepDataReachDetector;
use JesseGall\CodeCommandments\Vue\Codebase;
use PHPUnit\Framework\TestCase;

final class DeepDataReachDetectorTest extends TestCase
{
    public function test_flags_a_deep_reach_in_a_large_template(): void
    {
        $this->assertCount(1, $this->findBig('<span class="x">{{ order.customer.fullName }}</span>'));
        $this->assertCount(1, $this->findBig('<Avatar :src="order.customer.avatarUrl" />'));
    }

    public function test_ignores_a_deep_reach_in_a_small_template(): void
    {
        $found = new DeepDataReachDetector()->find(
            Codebase::fromString('<template><span>{{ order.customer.fullName }}</span></template>'),
        );

        $this->assertCount(0, $found);
    }

    public function test_ignores_shallow_access(): void
    {
        $this->assertCount(0, $this->findBig('<span>{{ order.total }}</span>'));
    }

    public function test_ignores_ref_value_and_collection_length(): void
    {
        $this->assertCount(0, $this->findBig('<div v-if="state.list.length > 0">{{ state.flag.value }}</div>'));
    }

    public function test_ignores_method_calls_and_dotted_string_literals(): void
    {
        // `order.customer.greet()` is a method call on order.customer (depth 1 of data);
        // `route('admin.orders.show')` is a string literal, not a reach.
        $this->assertCount(0, $this->findBig('<RouterLink :to="route(\'admin.orders.show\')">{{ order.customer.greet() }}</RouterLink>'));
    }

    /**
     * @return list<\JesseGall\CodeCommandments\Vue\ElementMatch>
     */
    private function findBig(string $body): array
    {
        $filler = str_repeat("  <p>row</p>\n", 55);

        return new DeepDataReachDetector()->find(
            Codebase::fromString("<template>\n{$filler}  {$body}\n</template>"),
        );
    }
}
