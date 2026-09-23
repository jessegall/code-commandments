<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Py\Node;

use Closure;
use JesseGall\CodeCommandments\Py\Expr\Expr;

/**
 * An `if` — its test, its block, and what follows it: an `elif` (another IfStmt), an `else` block, or nothing.
 */
final class IfStmt extends Node
{
    public function __construct(
        public readonly Expr $test,
        public readonly Block $body,
        public readonly IfStmt|Block|null $else = null,
    ) {}

    public function children(): array
    {
        return $this->else === null ? [$this->body] : [$this->body, $this->else];
    }

    public function expressions(): array
    {
        return [$this->test];
    }

    public function isBranchingConstruct(): bool
    {
        return true;
    }

    /**
     * The rungs of the chain this `if` opens — itself, then each `elif` in turn.
     *
     * @return list<IfStmt>
     */
    public function chain(): array
    {
        return [$this, ...($this->else instanceof self ? $this->else->chain() : [])];
    }

    public function isTwoWayBranch(Closure $tests): bool
    {
        return $this->else instanceof Block && $this->body->doesWork() && $this->else->doesWork() && $tests($this->test);
    }

    public function isTwoWayBranchBefore(Node $rest, Closure $tests): bool
    {
        $last = $this->body->body[array_key_last($this->body->body)] ?? null;

        return $this->else === null
            && $last?->isReturn() === true
            && $this->body->doesWork()
            && new Block([$rest])->doesWork()
            && $tests($this->test);
    }
}
