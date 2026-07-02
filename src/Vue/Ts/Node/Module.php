<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue\Ts\Node;

/**
 * The parsed `<script setup>` — its imports and its top-level statements (variable/function/
 * interface/type declarations and bare calls). It owns the structural queries the {@see
 * \JesseGall\CodeCommandments\Vue\Script} facade answers with, so those live HERE on the tree, not
 * as re-scans: find a declaration by name, the fields of a local type, the macro/composable calls,
 * and every bound local name.
 */
final class Module extends Node
{
    /**
     * @param  list<ImportDecl>  $imports
     * @param  list<Node>  $body
     */
    public function __construct(
        public readonly array $imports,
        public readonly array $body,
    ) {}

    public function variable(string $name): ?VariableDecl
    {
        foreach ($this->body as $node) {
            if ($node instanceof VariableDecl && in_array($name, $node->pattern->names(), true)) {
                return $node;
            }
        }

        return null;
    }

    public function functionNamed(string $name): ?FunctionDecl
    {
        return $this->firstOf(FunctionDecl::class, static fn (FunctionDecl $f): bool => $f->name === $name);
    }

    public function interface(string $name): ?InterfaceDecl
    {
        return $this->firstOf(InterfaceDecl::class, static fn (InterfaceDecl $i): bool => $i->name === $name);
    }

    public function typeAlias(string $name): ?TypeAliasDecl
    {
        return $this->firstOf(TypeAliasDecl::class, static fn (TypeAliasDecl $t): bool => $t->name === $name);
    }

    /**
     * A local `interface`/`type` declaration by name — the declarations the extract scribe carries
     * into a child. Returns the InterfaceDecl or TypeAliasDecl (both render() and expose fields()).
     */
    public function typeDeclaration(string $name): InterfaceDecl|TypeAliasDecl|null
    {
        return $this->interface($name) ?? $this->typeAlias($name);
    }

    /**
     * The first call to $callee anywhere at the top level — a bare `defineProps<…>()` statement OR
     * the initializer of a `const props = defineProps<…>()`. So a macro is found however it's written.
     */
    public function call(string $callee): ?CallExpr
    {
        foreach ($this->body as $node) {
            if ($node instanceof CallExpr && $node->callee === $callee) {
                return $node;
            }

            if ($node instanceof VariableDecl && $node->initCall?->callee === $callee) {
                return $node->initCall;
            }
        }

        return null;
    }

    /**
     * The LOCAL type declarations (interface/type) reachable from $names, each `name => rendered
     * source`, resolved transitively — a carried type's own type dependencies are carried too. A
     * name that isn't declared locally (imported, or a built-in) is skipped: the child imports those
     * or they need no declaration. This is what lets an extracted child keep a prop typed
     * `EditableItem[]` compile — the parent-local `interface EditableItem` travels with it.
     *
     * @param  list<string>  $names
     * @return array<string, string>
     */
    public function localTypes(array $names): array
    {
        $rendered = [];
        $seen = [];
        $queue = array_values(array_unique($names));

        while ($queue !== []) {
            $name = array_shift($queue);

            if (isset($seen[$name])) {
                continue;
            }

            $seen[$name] = true;
            $declaration = $this->typeDeclaration($name);

            if ($declaration === null) {
                continue; // not a local declaration — imported or built-in
            }

            $rendered[$name] = $declaration->render();
            $queue = [...$queue, ...$declaration->references()];
        }

        return $rendered;
    }

    /**
     * Every local name bound in the script — declaration patterns plus function names.
     *
     * @return list<string>
     */
    public function localNames(): array
    {
        $names = [];

        foreach ($this->body as $node) {
            if ($node instanceof VariableDecl) {
                $names = [...$names, ...$node->pattern->names()];
            } elseif ($node instanceof FunctionDecl) {
                $names[] = $node->name;
            }
        }

        return $names;
    }

    public function render(): string
    {
        return implode("\n", array_map(static fn (Node $n): string => $n->render(), [...$this->imports, ...$this->body]));
    }

    /**
     * @template T of Node
     * @param  class-string<T>  $type
     * @param  callable(T): bool  $match
     * @return T|null
     */
    private function firstOf(string $type, callable $match): ?Node
    {
        foreach ($this->body as $node) {
            if ($node instanceof $type && $match($node)) {
                return $node;
            }
        }

        return null;
    }
}
