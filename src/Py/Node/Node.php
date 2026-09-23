<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Py\Node;

use Closure;
use JesseGall\CodeCommandments\Positioned;
use JesseGall\CodeCommandments\Py\Expr\Expr;
use JesseGall\CodeCommandments\SyntaxNode;
use JesseGall\PhpTypes\Option;

/**
 * A statement of a Python module, answering the walk hooks the TypeScript engine's nodes answer — what
 * it contains, the expressions it holds, what kind of its kind it is, what it declares, and the body
 * it runs as a function — so one tool reads either engine's tree the same way.
 */
abstract class Node implements SyntaxNode
{
    use Positioned;

    /**
     * The nodes this one contains — the blocks of a compound statement, the parameters of a function.
     *
     * @return list<Node>
     */
    public function children(): array
    {
        return [];
    }

    /**
     * The expressions this node holds at its own level, not its children's.
     *
     * @return list<Expr>
     */
    public function expressions(): array
    {
        return [];
    }

    /**
     * Every node beneath this one, at any depth, parents before their children.
     *
     * @return list<Node>
     */
    final public function descendants(): array
    {
        $all = [];

        foreach ($this->children() as $child) {
            $all = [...$all, $child, ...$child->descendants()];
        }

        return $all;
    }

    /**
     * Every expression written beneath this node, at any depth, each sub-expression included.
     *
     * @return list<Expr>
     */
    final public function expressionsWithin(): array
    {
        return array_merge([], ...array_map(
            static fn (self $node): array => array_merge([], ...array_map(static fn (Expr $expression): array => $expression->flatten(), $node->expressions())),
            $this->descendants(),
        ));
    }

    /**
     * Every expression this scope writes itself, at any depth, each sub-expression included — a function or
     * class nested inside is its own scope, and its body is left to it.
     *
     * @return list<Expr>
     */
    final public function ownExpressions(): array
    {
        $own = [];

        foreach ($this->children() as $child) {
            if ($child->isScope()) {
                continue;
            }

            $own = [
                ...$own,
                ...array_merge([], ...array_map(static fn (Expr $expression): array => $expression->flatten(), $child->expressions())),
                ...$child->ownExpressions(),
            ];
        }

        return $own;
    }

    /**
     * What tells this node from another of its kind beyond its children and expressions — a jump's
     * `break` or `continue`, an augmented assignment's operator. Empty for a kind with nothing more.
     */
    public function variant(): string
    {
        return '';
    }

    public function isReturn(): bool
    {
        return false;
    }

    /**
     * Does this statement leave its block for good — a `return`, a `raise`, a `continue` or a `break`?
     * Named as the backend names it: whatever follows it in the block is not an alternative.
     */
    public function isBailOut(): bool
    {
        return false;
    }

    /**
     * Does this statement CHOOSE — an `if`, a loop, a `match` — so what sits inside it runs
     * conditionally? Named as the backend names it.
     */
    public function isBranchingConstruct(): bool
    {
        return false;
    }

    /**
     * Is this a `return` of nothing — bare, `None`, `False`, or an empty value?
     */
    public function returnsAbsence(): bool
    {
        return false;
    }

    /**
     * The docstring this definition opens with — none for anything but a `def` or a `class`.
     *
     * @return Option<string>
     */
    public function docstring(): Option
    {
        return Option::none();
    }

    /**
     * Is this statement one two-way choice that $tests accepts, each side doing work of its own? False for
     * every statement but an `if`/`else` and a `return a if … else b`.
     *
     * @param  Closure(Expr): bool  $tests
     */
    public function isTwoWayBranch(Closure $tests): bool
    {
        return false;
    }

    /**
     * Is this an `if` $tests accepts whose arm does its work and returns, with $rest — the one statement
     * after it — as the other arm? The same two-way choice as an `if`/`else`, written without the `else`.
     *
     * @param  Closure(Expr): bool  $tests
     */
    public function isTwoWayBranchBefore(Node $rest, Closure $tests): bool
    {
        return false;
    }

    /**
     * Does this statement do nothing with what came before it — `pass`, `...`, a string left alone,
     * `continue`, or a `return` of nothing or of an empty value?
     */
    public function isNoOp(): bool
    {
        return false;
    }

    /**
     * The targets this statement assigns — none for a statement that assigns nothing.
     *
     * @return list<Expr>
     */
    public function writtenTargets(): array
    {
        return [];
    }

    /**
     * Does this statement declare a name the class or module holds — an assignment, or an annotated
     * field with or without a default?
     */
    public function isStateDeclaration(): bool
    {
        return false;
    }

    /**
     * Does this statement declare a constant — an `UPPER_CASE` name, which is how Python spells one, or
     * a name annotated `Final` or `ClassVar`?
     */
    public function declaresConstant(): bool
    {
        return false;
    }

    /**
     * Is this a scope of its own — a `def` or a `class` — so what is written inside it belongs to
     * it rather than to the statements around it?
     */
    public function isScope(): bool
    {
        return false;
    }

    /**
     * @return Option<Expr>
     */
    public function returnedValue(): Option
    {
        return Option::none();
    }

    public function isExpressionStatement(): bool
    {
        return false;
    }

    /**
     * Does this statement stand in for a body without doing anything — `pass`, `...`, a docstring, or
     * `raise NotImplementedError`?
     */
    public function isPlaceholder(): bool
    {
        return false;
    }

    /**
     * The names this node declares — a function's, a class's, a parameter's.
     *
     * @return list<string>
     */
    public function declaredNames(): array
    {
        return [];
    }

    /**
     * The block this node runs as a function — a `def`'s body. None for anything else.
     *
     * @return Option<Block>
     */
    public function functionBody(): Option
    {
        return Option::none();
    }

    /**
     * $expressions without the parts that are not there.
     *
     * @param  list<?Expr>  $expressions
     * @return list<Expr>
     */
    protected static function present(array $expressions): array
    {
        return array_values(array_filter($expressions, static fn (?Expr $expression): bool => $expression !== null));
    }
}
