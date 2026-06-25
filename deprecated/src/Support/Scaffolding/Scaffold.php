<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Scaffolding;

use JesseGall\PhpTypes\T_String;

/**
 * A support class the package can stamp into the consumer's namespace — the
 * building blocks the prophets recommend (Option, FromArrayOnly, …) so an
 * app doesn't have to hand-roll them.
 */
final class Scaffold
{
    public function __construct(
        public readonly string $name,
        public readonly string $className,
        public readonly string $stubFile,
        public readonly string $introducedIn,
        public readonly string $purpose,
        /**
         * Namespace segment under the consumer's scaffold namespace this class
         * lives in (e.g. `Resolvers\Predicates`). Empty = the flat root.
         */
        public readonly string $subNamespace = T_String::EMPTY,
    ) {}
}
