<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Hints;

use PhpParser\Node\Identifier;

/**
 * A resolved `Class::method(...)` static-call site — used to rewrite a renamed
 * factory's callers to `::from(...)` and to learn which classes are `::collect()`-ed.
 */
final class CallSite
{
    public function __construct(
        public readonly string $class,
        public readonly string $method,
        public readonly Identifier $nameNode,
        public readonly string $file,
    ) {}
}
