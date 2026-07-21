<?php

namespace Shop\Sockets;

use JesseGall\CodeCommandments\Sins\Backend\HandRolledWither;
use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Five withers, each re-spelling all six fields to change one. Add a seventh field and every one of
 * them has to be edited — the tax clone-with removes.
 */
final readonly class InputSocket
{
    public function __construct(
        public string $id,
        public string $label,
        public string $type,
        public bool $required,
        public ?string $value,
        public int $order,
    ) {}

    #[Sinful(HandRolledWither::class)]
    public function withValue(?string $value): self
    {
        return new self($this->id, $this->label, $this->type, $this->required, $value, $this->order);
    }

    /** Righteous: says only what changes, so a new field never touches this method. */
    #[Righteous(HandRolledWither::class)]
    public function withOrder(int $order): self
    {
        return clone($this, ['order' => $order]);
    }
}
