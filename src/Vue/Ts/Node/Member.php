<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue\Ts\Node;

/**
 * One member of an object type or interface — a {@see Property} (`a: T`) or a {@see Method}
 * (`m(): R`). Both have a name and resolve to a {@see type} (a method's is its function type), so a
 * consumer reading `defineProps<{…}>()` sees a uniform `name => type` map regardless of the form.
 */
abstract class Member extends Node
{
    public function __construct(
        public readonly string $name,
        public readonly bool $optional = false,
    ) {}

    /**
     * The member's type as a value type — for a method, its `(params) => Return` function type.
     */
    abstract public function type(): TypeNode;

    /**
     * @return list<string>
     */
    public function references(): array
    {
        return $this->type()->references();
    }
}
