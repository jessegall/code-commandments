<?php

namespace Shop\Sockets;

use JesseGall\CodeCommandments\Sins\Backend\HandRolledWither;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * A named-argument wither. The names make the re-threading readable, which is exactly why it survives
 * review — but the field list is still restated in full to stamp one status.
 */
final readonly class StepEntry
{
    public function __construct(
        public string $id,
        public string $name,
        public string $nodeId,
        public string $status,
        public int $depth,
        public int $attempt,
        public string $correlationId,
    ) {}

    #[Sinful(HandRolledWither::class)]
    public function stamp(string $status): self
    {
        return new self(
            id: $this->id,
            name: $this->name,
            nodeId: $this->nodeId,
            status: $status,
            depth: $this->depth,
            attempt: $this->attempt,
            correlationId: $this->correlationId,
        );
    }
}
