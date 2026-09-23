<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments;

use JesseGall\PhpTypes\Option;

/**
 * A statement-level node of a parsed module, TypeScript's and Python's alike — read through the walk
 * hooks every node answers, so a whole-tree reading ({@see SyntaxHash}) is written once for both.
 */
interface SyntaxNode
{
    /**
     * @return list<SyntaxNode>
     */
    public function children(): array;

    /**
     * @return list<SyntaxExpression>
     */
    public function expressions(): array;

    /**
     * Every node beneath this one, at any depth, parents before their children.
     *
     * @return list<SyntaxNode>
     */
    public function descendants(): array;

    public function variant(): string;

    /**
     * @return list<string>
     */
    public function declaredNames(): array;

    /**
     * The block this node runs as a function; none for a node that is not a function with a body.
     *
     * @return Option<SyntaxNode>
     */
    public function functionBody(): Option;

    public function isReturn(): bool;

    /**
     * The value a `return` hands back; none for a bare `return`, and for a node that is no return.
     *
     * @return Option<SyntaxExpression>
     */
    public function returnedValue(): Option;

    /**
     * Is this an expression standing as a statement — a call made for its effect?
     */
    public function isExpressionStatement(): bool;
}
