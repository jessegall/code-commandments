<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ts\Expr;

/**
 * A CALL, read off a template expression: the function it names and the source of each argument
 * it passes. What `@click="save(row.id)"` is, once the expression tree has been asked.
 */
final readonly class CallExpression
{
    /**
     * @param  list<string>  $arguments  each argument exactly as it is written
     */
    public function __construct(
        public string $name,
        public array $arguments,
    ) {}

    /**
     * How many arguments this call passes — the arity an emitted event must declare.
     */
    public function arity(): int
    {
        return count($this->arguments);
    }

    /**
     * The arguments as they are written AFTER a leading one — `''` for none, else `, a, b`. A
     * caller forwarding this call into another (`$emit('save', row.id)`) splices this on rather
     * than re-joining the list and deciding about the comma itself.
     */
    public function trailingArguments(): string
    {
        return $this->arguments === [] ? '' : ', ' . implode(', ', $this->arguments);
    }
}
