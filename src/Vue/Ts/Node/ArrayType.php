<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue\Ts\Node;

/**
 * An array type in postfix form — `T[]`. Its element carries the references; a parenthesised
 * element (`(A | B)[]`) renders through its {@see ParenType}.
 */
final class ArrayType extends TypeNode
{
    public function __construct(public readonly TypeNode $element) {}

    public function render(): string
    {
        return $this->element->render() . '[]';
    }

    public function references(): array
    {
        return $this->element->references();
    }
}
