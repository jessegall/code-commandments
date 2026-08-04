<?php

namespace Shop\Catalog;

use JesseGall\CodeCommandments\Sins\Backend\MatchDefaultReturnsNull;

use JesseGall\CodeCommandments\Testing\Fixed;
use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Models\Product;

/**
 * Labels a product's priority — the default arm swallows an unknown level as null
 * instead of failing on a case nobody handled.
 */
final class PriorityLabel
{
    #[Sinful(MatchDefaultReturnsNull::class)]
    public function for(Product $product): ?string
    {
        return match ($product->priority) {
            1 => 'urgent',
            2 => 'normal',
            3 => 'low',
            default => null,
        };
    }

    /**
     * The default arm throws a named exception, so an unhandled priority fails
     * loudly instead of being swallowed into null.
     */
    #[Fixed(MatchDefaultReturnsNull::class)]
    #[Righteous(MatchDefaultReturnsNull::class)]
    public function strictFor(Product $product): string
    {
        return match ($product->priority) {
            1 => 'urgent',
            2 => 'normal',
            3 => 'low',
            default => throw UnknownPriority::for($product->priority),
        };
    }

    /**
     * A `match (true)` is boolean-condition dispatch (if/elseif sugar) over an OPEN,
     * arbitrary set of predicates — not a closed value set — so its `default => null` is a
     * normal `else`, a declared `?string` fallback, NOT a swallowed unhandled case. There is
     * no missing case to throw on, so this must NOT be flagged.
     */
    #[Righteous(MatchDefaultReturnsNull::class)]
    public function displayName(Product $product): ?string
    {
        return match (true) {
            $product->priority > 3 => 'archived',
            $product->name !== '' => $product->name,
            default => null,
        };
    }
}
