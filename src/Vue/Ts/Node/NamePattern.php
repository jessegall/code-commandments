<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue\Ts\Node;

/**
 * A plain binding — `const x`. Binds the one name.
 */
final class NamePattern extends Pattern
{
    public function __construct(public readonly string $name) {}

    public function names(): array
    {
        return [$this->name];
    }

    public function render(): string
    {
        return $this->name;
    }
}
