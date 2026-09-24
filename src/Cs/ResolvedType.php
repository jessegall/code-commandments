<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cs;

/**
 * The type the compiler gave an expression — fully qualified, and whether it is annotated nullable.
 */
final readonly class ResolvedType
{
    /**
     * @param  list<string>  $inner  the named types inside a generic or an array, however deep
     */
    public function __construct(
        public string $name,
        public bool $nullable,
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
