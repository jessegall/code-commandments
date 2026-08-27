<?php

namespace Shop\Catalog;

use JesseGall\CodeCommandments\Sins\Backend\MatchDefaultReturnsNull;
use JesseGall\CodeCommandments\Testing\Fixed;
use RuntimeException;

/**
 * What the swallowed `default => null` becomes: the unhandled case, named. A priority nobody
 * mapped is a gap in the mapping, and this is the only thing that says so out loud.
 */
#[Fixed(MatchDefaultReturnsNull::class)]
final class UnknownPriority extends RuntimeException
{
    public static function for(int $priority): self
    {
        return new self("No label is mapped for priority {$priority}.");
    }
}
