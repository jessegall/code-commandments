<?php

namespace Shop\Realtime;

use JesseGall\CodeCommandments\Sins\Backend\BlankStringOnTheWire;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Where a till's live updates come from: a socket when the shop has one, polling otherwise. The
 * socket is a total `string`, and the browser decides there is no socket by finding it blank.
 */
final readonly class TillFeed
{
    #[Sinful(BlankStringOnTheWire::class)]
    public function __construct(
        public string $channel,
        public string $socket,
        public int $pollMs,
    ) {}

    public function slowedTo(int $pollMs): self
    {
        return new self($this->channel, $this->socket, $pollMs);
    }
}
