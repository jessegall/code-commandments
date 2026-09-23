<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments;

use JesseGall\PhpTypes\Option;

/**
 * What a clone rule reads off the body a matched node runs as a function — its fingerprints, and the
 * shapes too thin to be worth sharing — for a match whose `$node` is a {@see SyntaxNode}, in whichever
 * language its {@see SyntaxHash} reads.
 */
trait ReadsFunctionBody
{
    /**
     * @return class-string<SyntaxHash>
     */
    abstract protected static function syntaxHash(): string;

    /**
     * A formatting-blind fingerprint of the statements this node runs as a function, with the name it
     * runs them under left out — two names for one body are the same code. Empty for a node that is
     * not a function with a body.
     */
    public function bodyHash(): string
    {
        $hash = static::syntaxHash();

        return $this->node->functionBody()->mapOr('', $hash::of(...));
    }

    /**
     * Like {@see bodyHash}, but blind to local names and string/number literals too — two bodies with one
     * control-flow skeleton that differ only in what they call their locals and which constants they use
     * (a type-2 clone).
     */
    public function shapeHash(): string
    {
        $hash = static::syntaxHash();

        return $this->node->functionBody()->mapOr('', $hash::normalized(...));
    }

    /**
     * How many nodes and expressions make up the function body — a size floor for a clone rule, since
     * short bodies are alike by coincidence. Zero for a node that is not a function with a body.
     */
    public function bodyNodeCount(): int
    {
        $hash = static::syntaxHash();

        return $this->node->functionBody()->mapOr(0, $hash::weight(...));
    }

    /**
     * Is the function body exactly `return <expr>` — a descriptor or a one-line delegate, with no
     * control flow to hoist?
     */
    public function isSoleReturnExpression(): bool
    {
        return $this->soleStatement()->isSomeAnd(static fn (SyntaxNode $statement): bool => $statement->returnedValue()->isSome());
    }

    /**
     * The void twin of {@see isSoleReturnExpression}: a body that is exactly one expression statement —
     * a call handed on, an assignment made.
     */
    public function isSoleExpressionStatement(): bool
    {
        return $this->soleStatement()->isSomeAnd(static fn (SyntaxNode $statement): bool => $statement->isExpressionStatement());
    }

    /**
     * Is the function body a LOOKUP TABLE written as code — it calls nothing, and every answer it returns
     * is a constant (`case 'paid': return 'green'`)? Two of them that differ are two tables of data,
     * not one procedure written twice: hoisting either merely moves the data.
     */
    public function isLiteralLookup(): bool
    {
        return $this->node->functionBody()->isSomeAnd(static function (SyntaxNode $body): bool {
            $returns = [];

            foreach ($body->descendants() as $node) {
                foreach ($node->expressions() as $expression) {
                    if (array_any($expression->flatten(), static fn (SyntaxExpression $part): bool => $part->isCall())) {
                        return false;
                    }
                }

                if ($node->isReturn()) {
                    $returns[] = $node;
                }
            }

            return $returns !== [] && array_all($returns, static fn (SyntaxNode $return): bool => $return->returnedValue()->isSomeAnd(static fn (SyntaxExpression $value): bool => $value->isConstant()));
        });
    }

    /**
     * The one statement that counts in the function body — none for a body of any other length, or no body.
     *
     * @return Option<SyntaxNode>
     */
    private function soleStatement(): Option
    {
        $hash = static::syntaxHash();

        return $this->node->functionBody()
            ->filter(static fn (SyntaxNode $body): bool => count($hash::counted($body)) === 1)
            ->map(static fn (SyntaxNode $body): SyntaxNode => $hash::counted($body)[0]);
    }
}
