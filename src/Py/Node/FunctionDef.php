<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Py\Node;

use JesseGall\CodeCommandments\Py\Expr\Expr;
use JesseGall\PhpTypes\Option;

/**
 * A `def` — a module function or a method alike — with its decorators, parameters, return annotation
 * and body.
 */
final class FunctionDef extends Node
{
    /**
     * @param  list<Param>  $params
     * @param  list<Expr>  $decorators
     */
    public function __construct(
        public readonly string $name,
        public readonly array $params,
        public readonly Block $body,
        public readonly ?Expr $returns = null,
        public readonly array $decorators = [],
        public readonly bool $async = false,
    ) {}

    /**
     * `async` or nothing — an async one cannot share a body with its sync twin.
     */
    public function variant(): string
    {
        return $this->async ? 'async' : '';
    }

    public function children(): array
    {
        return [...$this->params, $this->body];
    }

    public function expressions(): array
    {
        return $this->decorators;
    }

    public function declaredNames(): array
    {
        return [$this->name];
    }

    public function functionBody(): Option
    {
        return Option::some($this->body);
    }

    /**
     * The annotation $name carries in this function — as one of its parameters, or as a local it
     * declares with one.
     *
     * @return Option<Expr>
     */
    public function annotationOf(string $name): Option
    {
        foreach ($this->params as $param) {
            if ($param->name === $name && $param->annotation !== null) {
                return Option::some($param->annotation);
            }
        }

        foreach ($this->body->descendants() as $statement) {
            if ($statement instanceof AnnAssign && $statement->target->dottedName() === $name) {
                return Option::some($statement->annotation);
            }
        }

        return Option::none();
    }
}
