<?php

namespace Shop\Ui\Tokens;

/**
 * A design token — the very bottom of the UI stack, which knows about nothing above it.
 */
final class Accent
{
    public function __construct(public readonly string $name) {}

    public function hex(): string
    {
        return $this->name === 'primary' ? '#1b5e20' : '#616161';
    }
}
