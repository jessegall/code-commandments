<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue\Ts\Node;

/**
 * A `typeof x` query type — the type of the value binding $target. The target is a value name, not a
 * type, so it references no named TYPE.
 */
final class TypeofType extends TypeNode
{
    public function __construct(public readonly string $target) {}

    public function render(): string
    {
        return "typeof {$this->target}";
    }
}
