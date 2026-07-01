<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Ast;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\NodeMatch;
use PHPUnit\Framework\TestCase;

/**
 * A project hangs its own predicates on the AST node by SUBCLASSING {@see NodeMatch} and
 * type-hinting the subclass in a `where` closure — the query reflects the closure's parameter and
 * hands it that node, so custom detectors read `$n->isVehicleClause()` like the built-ins. No
 * registration: the type hint IS the injection.
 */
final class NodeDecorationTest extends TestCase
{
    public function test_a_typed_closure_is_handed_the_decorator_node(): void
    {
        $flagged = Codebase::fromString('<?php new VehicleClause(); new Order();')
            ->whereNew()
            ->where(static fn (DecoratedNode $n): bool => $n->isVehicleClause())
            ->get();

        $this->assertCount(1, $flagged);
        $this->assertSame('VehicleClause', $flagged[0]->newClassName());
    }

    public function test_the_returned_match_is_the_base_node(): void
    {
        $matches = Codebase::fromString('<?php new Order();')->whereNew()->get();

        $this->assertContainsOnlyInstancesOf(NodeMatch::class, $matches);
        $this->assertSame(NodeMatch::class, $matches[0]::class, 'get() returns the base; decorators are per-closure');
    }

    public function test_the_node_can_reach_its_codebase(): void
    {
        $match = Codebase::fromString('<?php new Order();')->whereNew()->get()[0];

        $this->assertInstanceOf(Codebase::class, $match->codebase);
    }
}

/** A project's own node type — a domain predicate composed from the engine's AST helpers. */
final class DecoratedNode extends NodeMatch
{
    public function isVehicleClause(): bool
    {
        return str_ends_with($this->newClassName() ?? '', 'Clause');
    }
}
