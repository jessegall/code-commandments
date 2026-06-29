<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Hints;

use PhpParser\Node\Identifier;
use PhpParser\Node\Param;

/**
 * A Spatie `Data` object factory the rewriter cares about: a `public static` method
 * that builds an instance of its own class. `isFrom` marks one already named `from…`
 * (so `::from()` can dispatch to it); a non-`from` one is a rename candidate.
 */
final class Factory
{
    /**
     * @param  list<Param>  $params
     */
    public function __construct(
        public readonly string $name,
        public readonly Identifier $nameNode,
        public readonly array $params,
        public readonly bool $isFrom,
    ) {}
}
