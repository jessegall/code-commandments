<?php

namespace Shop\Realtime;

use JesseGall\CodeCommandments\Sins\Backend\BlankStringOnTheWire;
use JesseGall\CodeCommandments\Testing\Fixed;
use JesseGall\CodeCommandments\Testing\Righteous;

/**
 * Where the stock room's live counts come from. A shop with no socket has NO socket, and the type
 * says so — so the browser asks for absence instead of decoding a blank.
 */
#[Fixed(BlankStringOnTheWire::class)]
#[Righteous(BlankStringOnTheWire::class)]
final readonly class StockFeed
{
    public function __construct(
        public string $channel,
        public ?string $socket = null,
        public int $pollMs = 2000,
    ) {}

    public function isSocketed(): bool
    {
        return $this->socket !== null;
    }
}
