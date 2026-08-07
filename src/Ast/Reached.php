<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ast;

/**
 * One place a value ARRIVED at during a {@see ValueFlow} walk: the occurrence itself, the kind of
 * edge that carried it there (`assign`, `arg`, `return`, `field`, `element`, `from`), and the array
 * key it is travelling under — null when the value is being carried directly rather than inside an
 * array.
 */
final readonly class Reached
{
    public function __construct(
        public NodeMatch $match,
        public string $edge,
        public ?string $key = null,
    ) {}
}
