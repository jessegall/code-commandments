<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Ast;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\NodeMatch;
use JesseGall\CodeCommandments\Config;
use PHPUnit\Framework\TestCase;

/**
 * A project can hang its own predicates on the AST node by registering a {@see NodeMatch}
 * subclass — every query match is then an instance of it, in both the `where` closures and the
 * returned results, so custom detectors read `$n->isVehicleClause()` like the built-ins.
 */
final class NodeDecorationTest extends TestCase
{
    public function test_the_query_wraps_matches_in_the_decorator_class(): void
    {
        $codebase = Codebase::fromString('<?php new VehicleClause(); new Order();')->decorateWith(DecoratedNode::class);

        $matches = $codebase->whereNew()->get();

        $this->assertContainsOnlyInstancesOf(DecoratedNode::class, $matches);
    }

    public function test_a_custom_predicate_is_usable_in_where_and_on_the_result(): void
    {
        $codebase = Codebase::fromString('<?php new VehicleClause(); new Order();')->decorateWith(DecoratedNode::class);

        // ...in the `where` closure (the sentence-reading form the feature is for):
        $flagged = $codebase->whereNew()->where(static fn (DecoratedNode $n): bool => $n->isVehicleClause())->get();

        $this->assertCount(1, $flagged);
        $this->assertTrue($flagged[0]->isVehicleClause());
        $this->assertSame('VehicleClause', $flagged[0]->newClassName());
    }

    public function test_default_is_a_plain_node_match(): void
    {
        $matches = Codebase::fromString('<?php new Order();')->whereNew()->get();

        $this->assertContainsOnlyInstancesOf(NodeMatch::class, $matches);
        $this->assertSame(NodeMatch::class, $matches[0]::class, 'no decorator → the base class');
    }

    public function test_decorate_must_be_a_node_match_subclass(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Codebase::fromString('<?php 1;')->decorateWith(\stdClass::class);
    }

    public function test_a_typed_closure_gets_the_decorator_without_registration(): void
    {
        // reflection picks the class off the closure's parameter — no decorateWith() needed, so a
        // package's own node predicate works everywhere, including the fixture harness.
        $flagged = Codebase::fromString('<?php new VehicleClause(); new Order();')
            ->whereNew()
            ->where(static fn (DecoratedNode $n): bool => $n->isVehicleClause())
            ->get();

        $this->assertCount(1, $flagged);
        $this->assertContainsOnlyInstancesOf(NodeMatch::class, $flagged, 'the returned match is the base node');
    }

    public function test_the_node_can_reach_its_codebase(): void
    {
        $match = Codebase::fromString('<?php new Order();')->whereNew()->get()[0];

        $this->assertInstanceOf(Codebase::class, $match->codebase);
    }

    public function test_config_carries_the_decorators(): void
    {
        $this->assertSame([], new Config()->nodeClasses(), 'none by default');
        $this->assertSame([DecoratedNode::class], new Config()->decorate(DecoratedNode::class)->nodeClasses());
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
