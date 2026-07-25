<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\NodeMatch;
use JesseGall\CodeCommandments\Detectors\Backend\NamespaceCycleDetector;
use PHPUnit\Framework\TestCase;

final class NamespaceCycleDetectorTest extends TestCase
{
    public function test_flags_the_thinner_arrow_of_a_mutual_pair(): void
    {
        // Orders leans on Billing twice, Billing on Orders once. Both directions are the cycle, but
        // the one-place direction is the accidental one and the cheapest to cut.
        $code = <<<'PHP'
        <?php
        namespace App\Billing {
            class Invoice { public function order(): \App\Orders\Order { return new \App\Orders\Order; } }
        }
        namespace App\Orders {
            class Order { public function invoice(): \App\Billing\Invoice { return new \App\Billing\Invoice; } }
            class Receipt { public function invoice(): \App\Billing\Invoice { return new \App\Billing\Invoice; } }
        }
        PHP;

        $this->assertSame(['App\Billing\Invoice::order'], $this->scopes($code));
    }

    public function test_a_one_way_dependency_is_not_a_cycle(): void
    {
        // The whole point: direction is fine, it is the round TRIP that traps the two namespaces.
        $code = <<<'PHP'
        <?php
        namespace App\Orders { class Order {} }
        namespace App\Billing { class Invoice { public function order(): \App\Orders\Order { return new \App\Orders\Order; } } }
        PHP;

        $this->assertSame([], $this->scopes($code));
    }

    public function test_a_namespace_nested_inside_another_is_part_of_it_not_a_peer(): void
    {
        // Found in calibration: `App\Caching` and its own `App\Caching\Concerns` referencing each
        // other looked like a cycle, but a trait folder leaning on its parent is one unit talking to
        // itself — exactly the freedom two classes side by side in one namespace already have.
        $code = <<<'PHP'
        <?php
        namespace App\Caching { class MultiLock { public function api(): \App\Caching\Concerns\CachedApi { return new \App\Caching\Concerns\CachedApi; } } }
        namespace App\Caching\Concerns { class CachedApi { public function lock(): \App\Caching\MultiLock { return new \App\Caching\MultiLock; } } }
        PHP;

        $this->assertSame([], $this->scopes($code));
    }

    public function test_a_many_namespace_tangle_names_no_single_arrow(): void
    {
        // `A → B → C → A` IS a cycle, and deliberately not reported. No one arrow in it is the
        // mistake, so flagging all three (or picking one) tells a reader nothing they can act on.
        // The mutual PAIR is the case a person can actually decide, so that is the case reported.
        $code = <<<'PHP'
        <?php
        namespace App\A { class One { public function go(): \App\B\Two { return new \App\B\Two; } } }
        namespace App\B { class Two { public function go(): \App\C\Three { return new \App\C\Three; } } }
        namespace App\C { class Three { public function go(): \App\A\One { return new \App\A\One; } } }
        PHP;

        $this->assertSame([], $this->scopes($code));
    }

    public function test_a_reference_out_of_the_pair_into_clean_code_is_not_the_cycle(): void
    {
        // Cutting App\A → App\Clean would free nothing; only the arrow inside the pair is a finding.
        $code = <<<'PHP'
        <?php
        namespace App\Clean { class Helper {} }
        namespace App\A {
            class One {
                public function b(): \App\B\Two { return new \App\B\Two; }
                public function helper(): \App\Clean\Helper { return new \App\Clean\Helper; }
            }
        }
        namespace App\B { class Two { public function a(): \App\A\One { return new \App\A\One; } } }
        PHP;

        $this->assertSame(['App\A\One::b'], $this->scopes($code));
    }

    public function test_classes_in_one_namespace_referencing_each_other_are_not_a_cycle(): void
    {
        // A namespace is the unit here. Two classes side by side in it may lean on each other freely.
        $code = <<<'PHP'
        <?php
        namespace App\Orders;
        class Order { public function line(): Line { return new Line; } }
        class Line { public function order(): Order { return new Order; } }
        PHP;

        $this->assertSame([], $this->scopes($code));
    }

    public function test_a_vendor_namespace_is_outside_the_graph(): void
    {
        // Nothing here can change the direction of a class the scan never declared, so it can never
        // be half of a cycle — otherwise every framework base class would fabricate one.
        $code = <<<'PHP'
        <?php
        namespace App\Orders { class Order extends \Illuminate\Database\Eloquent\Model { public function m(): \Illuminate\Support\Collection { return new \Illuminate\Support\Collection; } } }
        PHP;

        $this->assertSame([], $this->scopes($code));
    }

    /**
     * @return list<string>
     */
    private function scopes(string $code): array
    {
        $hits = new NamespaceCycleDetector()->find(Codebase::fromString($code));
        $scopes = array_map(static fn (NodeMatch $m): string => $m->scope(), $hits);
        sort($scopes);

        return $scopes;
    }
}
