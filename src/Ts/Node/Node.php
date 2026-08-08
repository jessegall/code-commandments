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
    /**
     * The `[start, end)` byte range this node occupies IN THE MODULE SOURCE it was parsed from —
     * 0/0 for a node built by hand rather than parsed. Module-relative on purpose: the parser is
     * handed a string and knows nothing of the file it came from, so turning this into a
     * `file:line` is {@see \JesseGall\CodeCommandments\Ts\ModuleFile}'s job, which owns both the
     * path and the offset the script block begins at.
     */
    public private(set) int $start = 0;

    public private(set) int $end = 0;

    abstract public function render(): string;

    /**
     * Stamp this node with the source range it was parsed from, and return it — the parser's one
     * way to record a position, written here so no subclass has to thread two more constructor
     * arguments through for it. Write access is the base class's alone ({@see $start}), so a node
     * cannot be moved once the parser has placed it.
     */
    public function locatedAt(int $start, int $end): static
    {
        $this->start = $start;
        $this->end = $end;

        return $this;
    }

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
