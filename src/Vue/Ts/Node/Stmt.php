<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue\Ts\Node;

use JesseGall\CodeCommandments\Vue\Expr\Expr;

/**
 * A STATEMENT — the branches, loops, returns and throws a function body is made of. The predicate
 * names are the backend's on purpose ({@see \JesseGall\CodeCommandments\Ast\AstNode}): a
 * guard-clause or null-handling rule is about a shape both languages have, so it can only put one
 * question to both engines if the two answer to the same words.
 */
abstract class Stmt extends Node
{
    /**
     * The expressions this statement holds AT ITS OWN LEVEL — a branch's test, a return's value.
     * Not its sub-statements' ({@see children} reaches those), so each expression is reported by
     * the one statement that owns it.
     *
     * @return list<Expr>
     */
    public function expressions(): array
    {
        return [];
    }

    /**
     * Does this statement BRANCH — an `if`, a `switch`, a `try`? The question a nesting-depth or
     * guard-clause rule asks, named as the backend names it.
     */
    public function isBranchingConstruct(): bool
    {
        return false;
    }

    public function isThrow(): bool
    {
        return false;
    }

    public function isReturn(): bool
    {
        return false;
    }

    public function isLoop(): bool
    {
        return false;
    }

    /**
     * Does this statement LEAVE the enclosing function — a `return` or a `throw`? What makes a
     * leading `if` a GUARD rather than half of a two-armed decision.
     */
    public function isExitPoint(): bool
    {
        return $this->isReturn() || $this->isThrow();
    }
}
