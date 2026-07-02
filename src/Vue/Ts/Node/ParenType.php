<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue\Ts\Node;

/**
 * A parenthesised type — `(A | B)`, kept so precedence (`(A | B)[]`, a parenthesised function type)
 * re-renders faithfully rather than being flattened.
 */
final class ParenType extends TypeNode
{
    public function __construct(public readonly TypeNode $inner) {}

    public function render(): string
    {
        return '(' . $this->inner->render() . ')';
    }

    public function references(): array
    {
        return $this->inner->references();
    }
}
