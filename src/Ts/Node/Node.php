<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ts\Node;

use JesseGall\CodeCommandments\Ts\Expr\Expr;

/**
 * The root of the `<script setup>` syntax tree — every declaration, pattern, and type is a Node.
 * Each renders back to valid TypeScript ({@see render}), so a type the parser understood can be
 * re-emitted into a generated component exactly, and a {@see TypeNode} additionally reports the
 * type names it {@see TypeNode::references} (for carrying a local type into an extracted child).
 */
abstract class Node
{
    use \JesseGall\CodeCommandments\Ts\Positioned;

    abstract public function render(): string;


    /**
     * The nodes this one CONTAINS — a module's statements, a function's parameters, a class's
     * members; empty for a leaf, which is most of them. The single hook a whole-codebase walk
     * descends through, so a selector reaches a parameter inside a method inside a class without
     * knowing that shape.
     *
     * @return list<self>
     */
    public function children(): array
    {
        return [];
    }

    /**
     * The expressions this node holds AT ITS OWN LEVEL — a branch's test, a return's value, a
     * declaration's initializer. Not its children's ({@see children} reaches those), so each
     * expression is reported by the one node that owns it.
     *
     * Declared here rather than on {@see Stmt} because a declaration holds expressions too: the call
     * in `const node = root.querySelector(…)` is a call like any other, and a rule that could not
     * see it would be blind to most of what a module actually does.
     *
     * @return list<Expr>
     */
    public function expressions(): array
    {
        return [];
    }

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
