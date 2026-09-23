<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ts;

use JesseGall\CodeCommandments\Located;
use JesseGall\CodeCommandments\Span;
use JesseGall\CodeCommandments\Ts\Expr\Expr;
use JesseGall\CodeCommandments\Ts\Node\BlockStmt;
use JesseGall\CodeCommandments\Ts\Node\CallExpr;
use JesseGall\CodeCommandments\Ts\Node\ExprStmt;
use JesseGall\CodeCommandments\Ts\Node\MethodDecl;
use JesseGall\CodeCommandments\Ts\Node\Node;
use JesseGall\CodeCommandments\Ts\Node\ReturnStmt;
use JesseGall\PhpTypes\Option;

/**
 * A matched TypeScript {@see Node} that knows WHERE it is — the module-space twin of
 * {@see ElementMatch}, and the frontend's answer to the backend's {@see \JesseGall\CodeCommandments\Ast\NodeMatch}.
 * Non-final by design: subclass it to hang domain predicates a `where` closure can type-hint.
 */
class NodeMatch implements Located
{
    public function __construct(
        public readonly Node $node,
        public readonly ModuleFile $module,
    ) {}

    /**
     * The name this node declares — a function's, a class's, a parameter's. Empty for a node that
     * declares none, which is every statement.
     */
    public function name(): string
    {
        return $this->node->declaredNames()[0] ?? '';
    }

    public function line(): int
    {
        return $this->module->lineAt($this->node->start);
    }

    /**
     * The expressions this node holds at its own level, each sub-expression included.
     *
     * @return list<Expr>
     */
    public function expressions(): array
    {
        $all = [];

        foreach ($this->node->expressions() as $expression) {
            $all = [...$all, ...$expression->flatten()];
        }

        return $all;
    }

    /**
     * A formatting-blind fingerprint of the statements this node runs as a function, with the name it
     * runs them under left out — two names for one body are the same code. Empty for a node that is
     * not a function with a body.
     */
    public function bodyHash(): string
    {
        return $this->node->functionBody()->mapOr('', StructuralHash::of(...));
    }

    /**
     * Like {@see bodyHash}, but blind to local names and string/number literals too — two bodies with one
     * control-flow skeleton that differ only in what they call their locals and which constants they use
     * (a type-2 clone).
     */
    public function shapeHash(): string
    {
        return $this->node->functionBody()->mapOr('', StructuralHash::normalized(...));
    }

    /**
     * How many nodes and expressions make up the function body — a size floor for a clone rule, since
     * short bodies are alike by coincidence. Zero for a node that is not a function with a body.
     */
    public function bodyNodeCount(): int
    {
        return $this->node->functionBody()->mapOr(0, StructuralHash::weight(...));
    }

    /**
     * Is this a class's `constructor` — structure every class declares for itself, which two classes
     * cannot share however alike they read?
     */
    public function isConstructorDeclaration(): bool
    {
        return $this->node instanceof MethodDecl && $this->node->isConstructor();
    }

    /**
     * Is the function body exactly `return <expr>;` — a descriptor or a one-line delegate, with no
     * control flow to hoist?
     */
    public function isSoleReturnExpression(): bool
    {
        return $this->soleStatement()->isSomeAnd(static fn (Node $statement): bool => $statement instanceof ReturnStmt && $statement->value !== null);
    }

    /**
     * The void twin of {@see isSoleReturnExpression}: a body that is exactly one expression statement —
     * a call handed on, an assignment made.
     */
    public function isSoleExpressionStatement(): bool
    {
        return $this->soleStatement()->isSomeAnd(static fn (Node $statement): bool => $statement instanceof ExprStmt || $statement instanceof CallExpr);
    }

    /**
     * Is the function body a LOOKUP TABLE written as code — it calls nothing, and every answer it returns
     * is a constant (`case 'paid': return 'green'`)? Two of them that differ are two tables of data,
     * not one procedure written twice: hoisting either merely moves the data.
     */
    public function isLiteralLookup(): bool
    {
        return $this->node->functionBody()->isSomeAnd(static function (BlockStmt $body): bool {
            $returns = [];

            foreach ($body->descendants() as $node) {
                foreach ($node->expressions() as $expression) {
                    if (array_any($expression->flatten(), static fn (Expr $part): bool => $part->isCall())) {
                        return false;
                    }
                }

                if ($node instanceof ReturnStmt) {
                    $returns[] = $node;
                }
            }

            return $returns !== [] && array_all($returns, static fn (ReturnStmt $return): bool => $return->value?->isConstant() ?? false);
        });
    }

    /**
     * The one statement the function body holds — none for a body of any other length, or no body.
     *
     * @return Option<Node>
     */
    private function soleStatement(): Option
    {
        return $this->node->functionBody()
            ->filter(static fn (BlockStmt $body): bool => count($body->body) === 1)
            ->map(static fn (BlockStmt $body): Node => $body->body[0]);
    }

    public function file(): string
    {
        return $this->module->file;
    }

    public function location(): string
    {
        return $this->file() . ':' . $this->line();
    }

    /**
     * Where this match sits, so a scribe rewrites it the way it rewrites any other engine's match.
     */
    public function span(): Span
    {
        return $this->module->spanAt($this->node->start, $this->node->end);
    }

    /**
     * A short context for the report — what the node IS, named where it has a name.
     */
    public function scope(): string
    {
        $kind = self::shortName($this->node);

        return $this->name() === '' ? $kind : $kind . ' ' . $this->name();
    }

    private static function shortName(Node $node): string
    {
        $parts = explode('\\', $node::class);

        return $parts[count($parts) - 1];
    }
}
