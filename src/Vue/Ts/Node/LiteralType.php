<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue\Ts\Node;

/**
 * A literal type — a string (`'a'`), number (`42`), or the literal `true`/`false` used as a type.
 * The raw source text is kept verbatim (quotes and all), so it re-renders exactly.
 */
final class LiteralType extends TypeNode
{
    public function __construct(public readonly string $raw) {}

    public function render(): string
    {
        return $this->raw;
    }
}
