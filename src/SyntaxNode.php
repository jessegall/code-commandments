<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments;

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

    public function variant(): string;

    /**
     * @return list<string>
     */
    public function declaredNames(): array;
}
