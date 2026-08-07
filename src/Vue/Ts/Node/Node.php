<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue\Ts\Node;

/**
 * The root of the `<script setup>` syntax tree — every declaration, pattern, and type is a Node.
 * Each renders back to valid TypeScript ({@see render}), so a type the parser understood can be
 * re-emitted into a generated component exactly, and a {@see TypeNode} additionally reports the
 * type names it {@see TypeNode::references} (for carrying a local type into an extracted child).
 */
abstract class Node
{
    abstract public function render(): string;

    /**
     * The call to $callee this node IS, or carries as its initializer — null for a node that is
     * neither, which is most of them. Asked of every top-level node, so a macro is found however it
     * was written without {@see Module} having to know which kinds can hold one.
     */
    public function callTo(string $callee): ?CallExpr
    {
        return null;
    }

    /**
     * The names this node DECLARES into the module scope — empty for a node that declares none.
     *
     * @return list<string>
     */
    public function declaredNames(): array
    {
        return [];
    }
}
