<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments;

use BackedEnum;
use UnitEnum;

/**
 * An expression of a parsed module as its language's kind and the properties that kind carries — the
 * contract {@see ExpressionTree} fulfils, so a reading over expressions is written once for every language.
 */
interface SyntaxExpression
{
    public BackedEnum $kind { get; }

    /**
     * @var array<string, mixed>
     */
    public array $props { get; }

    public function is(UnitEnum $kind): bool;

    public function isCall(): bool;

    /**
     * @return list<static>
     */
    public function flatten(): array;
}
