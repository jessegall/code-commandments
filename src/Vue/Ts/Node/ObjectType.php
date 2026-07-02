<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue\Ts\Node;

/**
 * An object type literal — `{ a: T; m(): R }` — the shape a `defineProps<{…}>()` or an inline prop
 * type takes. Exposes its members as a `name => type` map ({@see fields}), the uniform view the
 * prop-typing consumers read. References union every member's.
 */
final class ObjectType extends TypeNode
{
    /**
     * @param  list<Member>  $members
     */
    public function __construct(public readonly array $members) {}

    /**
     * The members as `name => rendered value type` — a method member yields its `(…) => R` form.
     *
     * @return array<string, string>
     */
    public function fields(): array
    {
        $fields = [];

        foreach ($this->members as $member) {
            $fields[$member->name] = $member->type()->render();
        }

        return $fields;
    }

    public function render(): string
    {
        return '{ ' . implode('; ', array_map(static fn (Member $m): string => $m->render(), $this->members)) . ' }';
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
