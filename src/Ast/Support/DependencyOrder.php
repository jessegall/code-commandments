<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ast\Support;

/**
 * The namespaces of a codebase sorted so each comes after everything it points at — plus the ones
 * no order could place, because they sit in a cycle. Both halves are the same answer: a stack that
 * can be declared bottom-up, and the part of it that cannot until the cycle is broken.
 */
final readonly class DependencyOrder
{
    /**
     * @param  list<string>  $ordered  each after everything it references
     * @param  list<string>  $cyclic   unorderable — each reachable from the other
     */
    public function __construct(
        public array $ordered,
        public array $cyclic,
    ) {}

    /**
     * How many namespaces the order covers — placed and unplaceable together, which is every one
     * the graph knows.
     */
    public function total(): int
    {
        return count($this->ordered) + count($this->cyclic);
    }

    /**
     * Did any namespace fail to place — is some part of the stack circular?
     */
    public function hasCycles(): bool
    {
        return $this->cyclic !== [];
    }

    /**
     * Every namespace the order covers — the placeable ones in dependency order first, then the
     * ones a cycle left unplaced. What a caller walks when it must cover the whole stack and the
     * cyclic tail simply goes last.
     *
     * @return list<string>
     */
    public function all(): array
    {
        return [...$this->ordered, ...$this->cyclic];
    }
}
