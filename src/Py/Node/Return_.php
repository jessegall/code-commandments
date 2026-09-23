<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Py\Node;

use Closure;
use JesseGall\CodeCommandments\Py\Expr\Expr;
use JesseGall\PhpTypes\Option;

/**
 * A `return`, with the value it returns when it has one.
 */
final class Return_ extends Node
{
    public function __construct(public readonly ?Expr $value = null) {}

    public function expressions(): array
    {
        return self::present([$this->value]);
    }

    public function isReturn(): bool
    {
        return true;
    }

    /**
     * @return Option<Expr>
     */
    public function returnedValue(): Option
    {
        return Option::fromNullable($this->value);
    }

    public function isBailOut(): bool
    {
        return true;
    }

    public function isNoOp(): bool
    {
        return $this->returnsAbsence();
    }

    public function returnsAbsence(): bool
    {
        return $this->returnedValue()->isNoneOr(static fn (Expr $value): bool => $value->isAbsenceValue());
    }

    public function isTwoWayBranch(Closure $tests): bool
    {
        return $this->returnedValue()->isSomeAnd(static fn (Expr $value): bool => $value->choosesBetweenWork($tests));
    }
}
