<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue\Ts\Node;

use JesseGall\CodeCommandments\Vue\Expr\Expr;

/**
 * A loop — `for`, `for…of`, `for…in`, `while`, `do…while`. One node for all of them, because what a
 * rule asks is almost always "is this inside a loop" or "what does it range over", not which
 * keyword was written.
 */
final class LoopStmt extends Stmt
{
    /**
     * @param  string  $keyword  `for` | `for-of` | `for-in` | `while` | `do`
     * @param  list<Expr>  $head  the head's expressions — a `while`'s test, a `for`'s init/test/step,
     *         a `for…of`'s iterable
     */
    public function __construct(
        public readonly string $keyword,
        public readonly array $head,
        public readonly Node $body,
    ) {}

    public function expressions(): array
    {
        return $this->head;
    }

    public function children(): array
    {
        return [$this->body];
    }

    public function isLoop(): bool
    {
        return true;
    }

    public function isBranchingConstruct(): bool
    {
        return true;
    }

    public function render(): string
    {
        $head = implode('; ', array_map(static fn (Expr $e): string => $e->source(), $this->head));

        return "{$this->keyword} ({$head}) " . $this->body->render();
    }
}
