<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Ast\Support;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\Support\NamespaceGraph;
use JesseGall\CodeCommandments\Detectors\Backend\NamespaceDependencyDetector;
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

        $this->assertSame(['App\Core', 'App\Web'], $order->ordered);
        $this->assertSame([], $order->cyclic);
    }

    public function test_a_cycle_is_reported_as_unorderable_rather_than_forced(): void
    {
        $code = <<<'PHP'
        <?php
        namespace App\Core { class Money { public function p(): \App\Web\Page { return new \App\Web\Page; } } }
        namespace App\Web { class Page { public function m(): \App\Core\Money { return new \App\Core\Money; } } }
        PHP;

        $order = $this->graph($code)->dependencyOrder();

        $this->assertSame([], $order->ordered);
        $this->assertSame(['App\Core', 'App\Web'], $order->cyclic);
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

    public function test_the_emitted_shape_permits_a_nested_pair_in_both_directions(): void
    {
        // The graph drops a nested pair's arrows — for DIRECTION the two are one unit — but a
        // declaration that names both must still permit the traffic between them.
        $code = <<<'PHP'
        <?php
        namespace App\Ai { class Agent { public function c(): \App\Ai\Contracts\Tool { return new \App\Ai\Contracts\Tool; } } }
        namespace App\Ai\Contracts { class Tool { public function a(): \App\Ai\Agent { return new \App\Ai\Agent; } } }
        PHP;

        $this->assertSame([], $this->graph($code)->edges(), 'the direction graph ignores a nested pair');
        $this->assertSame([
            'App\Ai' => ['App\Ai\Contracts'],
            'App\Ai\Contracts' => ['App\Ai'],
        ], $this->graph($code)->currentShape());
    }

    public function test_the_emitted_shape_names_what_each_layer_already_reaches(): void
    {
        $shape = $this->graph($this->stack())->currentShape();

        $this->assertSame(['App\Core' => [], 'App\Web' => ['App\Core']], $shape);
    }

    public function test_a_freshly_emitted_declaration_passes_its_own_judgement(): void
    {
        // The invariant the whole command rests on: what it writes must be green on the next judge,
        // or it hands you a config that immediately fails. Every shape below mixes the traps —
        // nested namespaces, siblings that reference each other, and a mutual pair.
        $sources = [
            'nested + siblings' => <<<'PHP'
                <?php
                namespace App\Ui { class Panel { public function e(): \App\Ui\Enums\Size { return \App\Ui\Enums\Size::Big; } } }
                namespace App\Ui\Enums { enum Size { case Big; } }
                namespace App\Support { class Str { public function e(): \App\Support\Enums\Case_ { return new \App\Support\Enums\Case_; } } }
                namespace App\Support\Enums { class Case_ {} }
                namespace App\Mcp { class Server { public function s(): \App\Support\Str { return new \App\Support\Str; } } }
                PHP,
            'a mutual pair' => <<<'PHP'
                <?php
                namespace App\Billing { class Invoice { public function o(): \App\Orders\Order { return new \App\Orders\Order; } } }
                namespace App\Orders { class Order { public function i(): \App\Billing\Invoice { return new \App\Billing\Invoice; } } }
                PHP,
        ];

        foreach ($sources as $label => $source) {
            $codebase = Codebase::fromString($source);
            $detector = new NamespaceDependencyDetector();

            foreach (NamespaceGraph::forCodebase($codebase)->currentShape() as $layer => $mayUse) {
                $detector->layer($layer, $mayUse);
            }

            $this->assertSame([], $detector->find($codebase), "{$label}: the emitted declaration must judge clean");
        }
    }

    public function test_the_floor_is_the_emitted_layers_that_reach_nothing_of_yours(): void
    {
        $this->assertSame(['App\Core' => []], $this->graph($this->stack())->floorShape());
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
