<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue\Ts\Node;

/**
 * A tuple type — `[string, number]`, `[]`. Its elements carry the references.
 */
final class TupleType extends TypeNode
{
    /**
     * @param  list<TypeNode>  $elements
     */
    public function __construct(public readonly array $elements) {}

    public function render(): string
    {
        return '[' . implode(', ', array_map(static fn (TypeNode $e): string => $e->render(), $this->elements)) . ']';
    }

    public function references(): array
    {
        $names = [];

        foreach ($this->elements as $element) {
            $names = [...$names, ...$element->references()];
        }

        return $names;
    }
}
