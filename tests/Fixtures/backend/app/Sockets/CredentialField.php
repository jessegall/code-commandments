<?php

namespace Shop\Sockets;

use JesseGall\CodeCommandments\Sins\Backend\HandRolledWither;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * A credential slot on a channel's settings form. Two slots change at once when it is revealed, which
 * is still ONE intent spelled as a full rebuild of everything else — and `new static` re-threads the
 * field list exactly as `new self` does.
 */
final readonly class CredentialField
{
    public function __construct(
        public string $key,
        public string $label,
        public string $group,
        public string $secret,
        public bool $masked,
    ) {}

    public function isEmpty(): bool
    {
        return $this->secret === '';
    }

    public function display(): string
    {
        return $this->masked ? str_repeat('*', 8) : $this->secret;
    }

    public function belongsTo(string $group): bool
    {
        return $this->group === $group;
    }

    #[Sinful(HandRolledWither::class)]
    public function revealed(string $secret): static
    {
        return new static(
            key: $this->key,
            label: $this->label,
            group: $this->group,
            secret: $secret,
            masked: false,
        );
    }
}
