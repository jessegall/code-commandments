<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue\Ts\Node;

/**
 * A union (`A | B`) or an intersection (`A & B`) — the same shape, one operator. Members render
 * joined by ` {operator} `; references union every member's.
 */
final class CompositeType extends TypeNode
{
    use AggregatesMemberReferences;

    /**
     * @param  '|'|'&'  $operator
     * @param  list<TypeNode>  $members
     */
    public function __construct(
        public readonly string $operator,
        public readonly array $members,
    ) {}

    /**
     * A UNION admits absence when any member does; an intersection cannot — `A & null` is never a
     * value, so only `|` is asked.
     */
    public function admitsAbsence(): bool
    {
        if ($this->operator !== '|') {
            return false;
        }

        foreach ($this->members as $member) {
            if ($member->admitsAbsence()) {
                return true;
            }
        }

        return false;
    }

    public function render(): string
    {
        return implode(" {$this->operator} ", array_map(static fn (TypeNode $m): string => $m->render(), $this->members));
    }

    /**
     * Each member unwrapped, then made readable again: unwrapping `Ref<V | null> | null` nests a
     * `V | null` union inside this one, so a same-operator member is FLATTENED in and the
     * now-duplicate `null` collapsed. A union of one is that one type.
     */
    public function unwrapRef(): TypeNode
    {
        $flattened = [];

        foreach ($this->members as $member) {
            $unwrapped = $member->unwrapRef();

            $flattened = $unwrapped instanceof self && $unwrapped->operator === $this->operator
                ? [...$flattened, ...$unwrapped->members]
                : [...$flattened, $unwrapped];
        }

        $seen = [];
        $unique = array_values(array_filter($flattened, static function (TypeNode $member) use (&$seen): bool {
            $rendered = $member->render();

            return isset($seen[$rendered]) ? false : ($seen[$rendered] = true);
        }));

        return count($unique) === 1 ? $unique[0] : new self($this->operator, $unique);
    }
}
