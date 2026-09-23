<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Py\Node;

/**
 * A `break` or a `continue`.
 */
final class Jump extends Node
{
    public function __construct(public readonly string $keyword) {}

    public function variant(): string
    {
        return $this->keyword;
    }

    public function isBailOut(): bool
    {
        return true;
    }

    public function isNoOp(): bool
    {
        return $this->keyword === 'continue';
    }
}
