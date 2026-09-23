<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cs;

/**
 * The type the compiler gave an expression — fully qualified, and whether it is annotated nullable.
 */
final readonly class ResolvedType
{
    public function __construct(
        public string $name,
        public bool $nullable,
    ) {}
}
