<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Py;

use JesseGall\CodeCommandments\Py\Codebase;
use JesseGall\CodeCommandments\Py\NodeMatch;
use PHPUnit\Framework\TestCase;

/**
 * How deep a statement sits in the choices of its own function: each block an `if`, a loop or a `match`
 * owns is a level; an `elif` is a rung of its chain, not a level; a `try` or `with` never counts; and
 * the count starts again inside a nested `def`.
 */
final class BranchingDepthTest extends TestCase
{
    private const string SOURCE = <<<'PY'
        def ship(orders):
            for order in orders:
                if order.paid:
                    try:
                        with lock():
                            while order.pending:
                                book(order)
                    except Busy:
                        pass
                elif order.late:
                    if order.urgent:
                        chase(order)

            def inner():
                if ready:
                    go()
        PY;

    public function test_each_branching_block_is_one_level(): void
    {
        $this->assertSame(['book' => 3, 'chase' => 3, 'go' => 1], $this->depthsOfCalls());
    }

    public function test_an_elif_is_a_rung_not_a_level(): void
    {
        $elifs = array_values(array_filter($this->statements(), static fn (NodeMatch $match): bool => $match->isElif()));

        $this->assertCount(1, $elifs);
        $this->assertSame(10, $elifs[0]->line());
        $this->assertSame(1, $elifs[0]->branchingDepth());
    }

    /**
     * @return array<string, int>
     */
    private function depthsOfCalls(): array
    {
        $depths = [];

        foreach ($this->statements() as $match) {
            foreach (['book', 'chase', 'go'] as $call) {
                if (str_starts_with($this->text($match), "{$call}(")) {
                    $depths[$call] = $match->branchingDepth();
                }
            }
        }

        return $depths;
    }

    private function text(NodeMatch $match): string
    {
        return substr(self::SOURCE, $match->node->start, $match->node->end - $match->node->start);
    }

    /**
     * @return list<NodeMatch>
     */
    private function statements(): array
    {
        return Codebase::fromString(self::SOURCE)->whereStatement()->get();
    }
}
