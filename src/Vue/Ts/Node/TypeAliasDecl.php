<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue\Ts\Node;

/**
 * A `type Name = …` alias. When the aliased type is an {@see ObjectType} its {@see fields} read like
 * an interface's; otherwise it's a union/ref/etc. {@see render} re-emits the whole alias so it can
 * be carried into an extracted child.
 */
final class TypeAliasDecl extends Node
{
    public function __construct(
        public readonly string $name,
        public readonly TypeNode $type,
        public readonly string $header = '',
    ) {}

    /**
     * The fields when the alias is an object shape, else an empty map.
     *
     * @return array<string, string>
     */
    public function fields(): array
    {
        return $this->type instanceof ObjectType ? $this->type->fields() : [];
    }

    public function render(): string
    {
        return "type {$this->name}{$this->header} = {$this->type->render()};";
    }

    /**
     * The named types the aliased type references — for carrying its dependencies alongside it.
     *
     * @return list<string>
     */
    public function references(): array
    {
        return $this->type->references();
    }
}
