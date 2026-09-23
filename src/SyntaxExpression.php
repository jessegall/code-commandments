<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments;


/**
 * An expression of a parsed module as its language's kind and the properties that kind carries — the
 * contract {@see ExpressionTree} fulfils, so a reading over expressions is written once for every language.
 */
interface SyntaxExpression
{
    /**
     * @var array<string, mixed>
     */
    public array $props { get; }

    /**
     * What kind of expression this is, by name — `call`, `InvocationExpression` — as its language names it.
     */
    public function kindName(): string;

    public function isCall(): bool;

    /**
     * Is this a constant written in the source — a literal, with nothing computed inside it?
     */
    public function isConstant(): bool;

    /**
     * @return list<static>
     */
    public function flatten(): array;
}
