<?php

namespace Shop\Checkout;

use JesseGall\CodeCommandments\Sins\Backend\PositionalTupleReturn;

use JesseGall\CodeCommandments\Testing\Fixed;
use JesseGall\CodeCommandments\Testing\Sinful;

final class CheckoutUnpacker
{
    /**
     * @return array{0: string, 1: list<string>, 2: int, 3: string}
     */
    #[Sinful(PositionalTupleReturn::class)]
    public function unpack(string $reference): array
    {
        $parts = explode(':', $reference);
        $order = $parts[0];
        $lines = array_slice($parts, 1);
        $count = count($lines);
        $currency = strtoupper(substr($order, 0, 3));

        return [$order, $lines, $count, $currency];
    }

    /**
     * The same reference, answered with a named result: the caller reads `->currency`, not `[3]`, so
     * adding a field never silently re-numbers what everyone else destructured.
     */
    #[Fixed(PositionalTupleReturn::class)]
    public function parse(string $reference): CheckoutReference
    {
        $parts = explode(':', $reference);

        return new CheckoutReference(
            order: $parts[0],
            lines: array_slice($parts, 1),
            currency: strtoupper(substr($parts[0], 0, 3)),
        );
    }
}

/* The tuple named: the four values that were positional now answer to what they are. */
#[Fixed(PositionalTupleReturn::class)]
final readonly class CheckoutReference
{
    /**
     * @param  list<string>  $lines
     */
    public function __construct(
        public string $order,
        public array $lines,
        public string $currency,
    ) {}

    public function lineCount(): int
    {
        return count($this->lines);
    }
}
