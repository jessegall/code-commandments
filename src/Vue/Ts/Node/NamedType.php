<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue\Ts\Node;

/**
 * A named type reference, optionally generic: `Foo`, `Foo<A, B>`, a qualified `App.Http.View.X`, or
 * a namespaced-generic `Ref<User>`. The {@see name} is the whole dotted path; {@see arguments} are
 * its type arguments. It references its own name plus every name its arguments reference — the hook
 * the extract scribe uses to carry a parent-local type into a child.
 */
final class NamedType extends TypeNode
{
    /**
     * @param  list<TypeNode>  $arguments
     */
    public function __construct(
        public readonly string $name,
        public readonly array $arguments = [],
    ) {}

    public function render(): string
    {
        if ($this->arguments === []) {
            return $this->name;
        }

        return $this->name . '<' . implode(', ', array_map(static fn (TypeNode $a): string => $a->render(), $this->arguments)) . '>';
    }

    public function references(): array
    {
        $names = [$this->name];

        foreach ($this->arguments as $argument) {
            $names = [...$names, ...$argument->references()];
        }

        return $names;
    }
}
