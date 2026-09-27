<?php

declare(strict_types=1);

namespace CodeCommandments\PhpBridge;

use JsonSerializable;

/**
 * A class the scan names that the scanned project's own autoloader failed on, and why: it stays outside the scan, and
 * the stream says so rather than dropping it unseen.
 */
final readonly class UnreadableSymbol implements JsonSerializable
{
    public function __construct(public string $symbol, public string $reason) {}

    public function jsonSerialize(): array
    {
        return ['symbol' => $this->symbol, 'reason' => $this->reason];
    }
}
