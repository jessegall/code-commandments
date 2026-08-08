<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue\Ts\Node;

use JesseGall\CodeCommandments\Vue\Expr\Expr;

/**
 * An `if (test) … else …`. The `else if` chain is modelled as an {@see otherwise} that IS another
 * `IfStmt`, which is what lets a rule walk a ladder rung by rung and ask whether every rung tests
 * the same subject.
 */
final class IfStmt extends Stmt
{
    public function __construct(
        public readonly Expr $test,
        public readonly Node $then,
        public readonly ?Node $otherwise = null,
    ) {}

    public function expressions(): array
    {
        return [$this->test];
    }

    public function children(): array
    {
        return array_values(array_filter([$this->then, $this->otherwise]));
    }

    public function isBranchingConstruct(): bool
    {
        return true;
    }

    /**
     * Is this a GUARD — a branch with no `else` whose body only leaves the function? The shape
     * `guard-clauses-and-flow` asks for at the top of a body, as opposed to a two-armed decision
     * wrapping the work.
     */
    public function isGuard(): bool
    {
        return $this->otherwise === null && $this->bodyExits();
    }

    /**
     * The `else if` rungs below this one, this statement first — a chain of three `else if`s reads
     * back as four IfStmts. What a rule counts to decide a ladder has become a dispatch.
     *
     * @return list<self>
     */
    public function chain(): array
    {
        $rungs = [$this];
        $next = $this->otherwise;

        while ($next instanceof self) {
            $rungs[] = $next;
            $next = $next->otherwise;
        }

        return $rungs;
    }

    /**
     * Does an `else` follow that is NOT another rung — a real terminal branch?
     */
    public function hasTerminalElse(): bool
    {
        $last = $this->chain()[count($this->chain()) - 1];

        return $last->otherwise !== null;
    }

    /**
     * Does every path out of the `then` body leave the function? A block counts when its LAST
     * statement exits, which is where a `return`/`throw` sits in a guard.
     */
    private function bodyExits(): bool
    {
        if ($this->then instanceof BlockStmt) {
            return $this->then->last()->isSomeAnd(static fn (Node $end): bool => $end instanceof Stmt && $end->isExitPoint());
        }

        return $this->then instanceof Stmt && $this->then->isExitPoint();
    }

    public function render(): string
    {
        $else = $this->otherwise !== null ? ' else ' . $this->otherwise->render() : '';

        return "if ({$this->test->source()}) " . $this->then->render() . $else;
    }
}
