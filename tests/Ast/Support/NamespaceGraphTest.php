<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Ast\Support;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\Support\NamespaceGraph;
use PHPUnit\Framework\TestCase;

final class NamespaceGraphTest extends TestCase
{
    public function test_folds_class_references_up_into_namespace_edges(): void
    {
        $graph = $this->graph($this->stack());

        $this->assertSame(['App\Core'], $graph->edges()['App\Web']);
        $this->assertArrayNotHasKey('App\Core', $graph->edges(), 'the floor references nothing of ours');
    }

    public function test_a_vendor_target_is_outside_the_graph(): void
    {
        // Nothing here can change the direction of a class the scan never declared.
        $code = <<<'PHP'
        <?php
        namespace App\Core { class Money extends \Illuminate\Database\Eloquent\Model {} }
        PHP;

        $this->assertSame([], $this->graph($code)->edges());
    }

    public function test_a_nested_namespace_is_part_of_its_parent_not_a_peer(): void
    {
        $code = <<<'PHP'
        <?php
        namespace App\Core { class Money { public function f(): \App\Core\Concerns\Roundable { return new \App\Core\Concerns\Roundable; } } }
        namespace App\Core\Concerns { class Roundable { public function m(): \App\Core\Money { return new \App\Core\Money(); } } }
        PHP;

        $this->assertSame([], $this->graph($code)->edges());
    }

    public function test_the_floor_is_depended_on_and_depends_on_nothing(): void
    {
        // App\Loose depends on nothing either, but nothing depends on IT — pinning it down would
        // buy no one anything, so it is not part of the floor.
        $code = <<<'PHP'
        <?php
        namespace App\Core { class Money {} }
        namespace App\Loose { class Stray {} }
        namespace App\Web { class Page { public function m(): \App\Core\Money { return new \App\Core\Money; } } }
        PHP;

        $this->assertSame(['App\Core'], $this->graph($code)->foundation());
    }

    public function test_dependency_order_puts_a_namespace_after_what_it_references(): void
    {
        $order = $this->graph($this->stack())->dependencyOrder();

        $this->assertSame(['App\Core', 'App\Web'], $order['ordered']);
        $this->assertSame([], $order['cyclic']);
    }

    public function test_a_cycle_is_reported_as_unorderable_rather_than_forced(): void
    {
        $code = <<<'PHP'
        <?php
        namespace App\Core { class Money { public function p(): \App\Web\Page { return new \App\Web\Page; } } }
        namespace App\Web { class Page { public function m(): \App\Core\Money { return new \App\Core\Money; } } }
        PHP;

        $order = $this->graph($code)->dependencyOrder();

        $this->assertSame([], $order['ordered']);
        $this->assertSame(['App\Core', 'App\Web'], $order['cyclic']);
    }

    public function test_a_mutual_pair_names_the_thinner_direction(): void
    {
        // Web leans on Core twice, Core on Web once — the single-place arrow is the one to cut.
        $code = <<<'PHP'
        <?php
        namespace App\Core { class Money { public function p(): \App\Web\Page { return new \App\Web\Page; } } }
        namespace App\Web {
            class Page { public function m(): \App\Core\Money { return new \App\Core\Money; } }
            class Panel { public function m(): \App\Core\Money { return new \App\Core\Money; } }
        }
        PHP;

        $this->assertSame([['App\Core', 'App\Web']], $this->graph($code)->mutualPairs());
    }

    private function stack(): string
    {
        return <<<'PHP'
        <?php
        namespace App\Core { class Money {} }
        namespace App\Web { class Page { public function total(): \App\Core\Money { return new \App\Core\Money; } } }
        PHP;
    }

    private function graph(string $code): NamespaceGraph
    {
        return NamespaceGraph::forCodebase(Codebase::fromString($code));
    }
}
