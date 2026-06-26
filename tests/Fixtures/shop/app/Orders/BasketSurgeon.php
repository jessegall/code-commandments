<?php

namespace Shop\Orders;

/**
 * Righteous twin for ParamResolvedFromParam: this resolves a line by id too, but the
 * BASKET is the subject — it is rebuilt with the line removed and handed back. The
 * container is a genuine co-subject, not packaging, so it must NOT be flagged.
 */
final class BasketSurgeon
{
    public function removeLine(Basket $basket, string $lineId): Basket
    {
        $line = $basket->lineById($lineId);

        return $basket->without($line);
    }
}

final class Basket
{
    /** @var array<string, BasketLine> */
    public array $lines = [];

    public function lineById(string $id): BasketLine
    {
        return $this->lines[$id];
    }

    public function without(BasketLine $line): Basket
    {
        return $this;
    }
}

final class BasketLine {}
