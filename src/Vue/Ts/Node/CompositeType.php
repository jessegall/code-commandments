<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue\Ts\Node;

/**
 * A union (`A | B`) or an intersection (`A & B`) — the same shape, one operator. Members render
 * joined by ` {operator} `; references union every member's.
 */
final class CompositeType extends TypeNode
{
    /**
     * @param  '|'|'&'  $operator
     * @param  list<TypeNode>  $members
     */
    public function __construct(
        public readonly string $operator,
        public readonly array $members,
    ) {}

    public function render(): string
    {
        return implode(" {$this->operator} ", array_map(static fn (TypeNode $m): string => $m->render(), $this->members));
    }

    public function references(): array
    {
        $names = [];

        foreach ($this->members as $member) {
            $names = [...$names, ...$member->references()];
        }

        return $names;
    }
}
