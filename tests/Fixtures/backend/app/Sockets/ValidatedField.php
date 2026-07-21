<?php

namespace Shop\Sockets;

use JesseGall\CodeCommandments\Sins\Backend\HandRolledWither;
use JesseGall\CodeCommandments\Testing\Righteous;

/**
 * The constructor NORMALISES its input, so `new self(...)` and `clone($this, [...])` are not the same
 * thing — clone-with copies the raw value and skips the normalisation. This wither must keep
 * rebuilding through the constructor, and the rule must leave it alone.
 */
final readonly class ValidatedField
{
    public string $key;

    public function __construct(
        string $key,
        public string $label,
        public string $hint,
        public string $value,
        public bool $masked,
    ) {
        $this->key = strtolower(trim($key));
    }

    #[Righteous(HandRolledWither::class)]
    public function withValue(string $value): self
    {
        return new self($this->key, $this->label, $this->hint, $value, $this->masked);
    }
}
