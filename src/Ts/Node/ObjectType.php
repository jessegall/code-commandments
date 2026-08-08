<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ts\Node;

/**
 * An object type literal — `{ a: T; m(): R }` — the shape a `defineProps<{…}>()` or an inline prop
 * type takes. Exposes its members as a `name => type` map ({@see fields}), the uniform view the
 * prop-typing consumers read. References union every member's.
 */
final class ObjectType extends TypeNode
{
    use AggregatesMemberReferences;
    use MembersAsFields;

    /**
     * @param  list<Member>  $members
     */
    public function __construct(public readonly array $members) {}

    public function render(): string
    {
        if ($this->members === []) {
            return '{}';
        }

        return '{ ' . implode('; ', array_map(static fn (Member $m): string => $m->render(), $this->members)) . ' }';
    }

    public function fieldsWith(callable $declared): array
    {
        return $this->fields();
    }
}
