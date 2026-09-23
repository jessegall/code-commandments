<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cs;

/**
 * The method a call reaches, as the compiler resolved it: its containing type, its name, and its
 * parameter types, all fully qualified.
 */
final readonly class CallTarget
{
    /**
     * @param  list<string>  $parameters
     */
    public function __construct(
        public string $type,
        public string $name,
        public array $parameters,
    ) {}
}
