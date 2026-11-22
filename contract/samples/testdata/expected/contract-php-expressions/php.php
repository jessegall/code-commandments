<?php

function describe(array $items, int $count, string $name, object $thing): string
{
    // @contract kind/cast
    // @contract fact/cast/form = "checked"
    // @contract role/cast/operand -> itemIndex
    // @contract role/cast/targetType -> castType
    // @contract kind/index
    // @contract role/index/subject -> itemsRef
    // @contract role/index/key -> zeroKey
    // @label itemIndex index
    // @label castType type-expression
    // @label itemsRef reference 20
    // @label zeroKey literal
    $first = (int) $items[0];
    // @contract kind/conditional
    // @contract role/conditional/condition -> notOp
    // @contract role/conditional/consequent -> negOp
    // @contract role/conditional/alternative -> plusOp
    // @contract kind/unary at 14
    // @contract fact/unary/operator at 14 = "not"
    // @contract fact/unary/operator at 25 = "negate"
    // @contract fact/unary/operator at 35 = "plus"
    // @contract role/unary/operand at 14 -> notOperand
    // @label notOp unary 14
    // @label negOp unary 25
    // @label plusOp unary 35
    // @label notOperand reference 16
    $shown = ! $first ? -$count : +$count;
    $bits = ~$count;
    // @contract kind/type-test
    // @contract fact/type-test/form = "instanceof"
    // @contract role/type-test/subject -> thingRef
    // @contract role/type-test/testedType -> testedClass
    // @label thingRef reference 16
    // @label testedClass type-expression
    $counted = $thing instanceof Countable;
    $label = "Item {$name} of $count";
    // @contract kind/collection
    // @contract fact/collection/form = "array"
    // @contract role/collection/entries -> [spreadEntry, threeEntry]
    // @contract kind/entry at 13
    // @contract fact/entry/expansion at 13 = "positional"
    // @contract role/entry/value at 13 -> spreadValue
    // @label spreadEntry entry 13
    // @label threeEntry entry 24
    // @label spreadValue reference 16
    $all = [...$items, 3];
    // @contract kind/construction
    // @contract role/construction/callee -> dynClass
    // @label dynClass reference 17
    $made = new $name();

    return $label;
}

function total(int $count): int
{
    return $count;
}
