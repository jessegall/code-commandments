<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Hints;

use PhpParser\Node\Stmt\Class_;

/**
 * A Spatie `Data` class the rewriter found, with its source file and its object
 * factories.
 */
final class DataClass
{
    /**
     * @param  list<Factory>  $factories
     */
    public function __construct(
        public readonly string $file,
        public readonly Class_ $node,
        public readonly array $factories,
    ) {}
}
