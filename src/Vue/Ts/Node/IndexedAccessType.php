<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue\Ts\Node;

/**
 * An indexed access — `T['key']`, `Order['customer']`, `T[number]`. The workhorse of top-down prop
 * typing: a member chain in a parent becomes `Root['field']` down the data path. Both the object
 * type and the index type carry references.
 */
final class IndexedAccessType extends TypeNode
{
    public function __construct(
        public readonly TypeNode $object,
        public readonly TypeNode $index,
    ) {}

    public function render(): string
    {
        return $this->object->render() . '[' . $this->index->render() . ']';
    }

    public function references(): array
    {
        return [...$this->object->references(), ...$this->index->references()];
    }
}
