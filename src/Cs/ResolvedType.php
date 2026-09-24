<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cs;

/**
 * The type the compiler gave an expression — fully qualified, and whether it is annotated nullable.
 */
final readonly class ResolvedType
{
    /**
     * @param  bool  $isValueType  a struct, an enum or a primitive — a value copied, not an object referred to
     * @param  list<string>  $inner  the named types inside a generic or an array, however deep
     */
    public function __construct(
        public string $name,
        public bool $nullable,
        public bool $isValueType,
        public array $inner = [],
    ) {}

    /**
     * Every named type this type is made of — itself, nullability aside, and the types inside it.
     *
     * @return list<string>
     */
    public function namedTypes(): array
    {
        return [rtrim($this->name, '?'), ...$this->inner];
    }
}
