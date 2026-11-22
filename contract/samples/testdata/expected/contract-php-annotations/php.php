<?php

final class Tag
{
    public function __construct(public string $name = '') {}
}

// @contract kind/annotation
// @contract kind/argument
// @contract role/type/attributes -> [itemAttr]
// @contract role/annotation/callee -> itemCallee
// @contract role/annotation/arguments -> [itemArg]
// @contract role/argument/value -> itemValue
// @label itemAttr annotation
// @label itemCallee reference
// @label itemArg argument
// @label itemValue literal
#[Tag('t')] final class Item {}

final class Holder
{
    // @contract role/constant/attributes -> [maxAttr]
    // @label maxAttr annotation
    #[Tag('c')] public const int MAX = 3;

    // @contract role/field/attributes -> [sizeAttr]
    // @label sizeAttr annotation
    #[Tag('f')] public int $size = 0;

    // @contract role/function/attributes -> [runAttr]
    // @contract role/function/parameters -> [byParam]
    // @label runAttr annotation
    #[Tag('m')] public function run(
        // @contract role/parameter/attributes -> [byAttr]
        // @label byParam parameter
        // @label byAttr annotation
        #[Tag('p')] int $by,
    ): void {}
}

enum Suit: string
{
    // @contract role/enum-case/attributes -> [heartsAttr]
    // @label heartsAttr annotation
    #[Tag('e')] case Hearts = 'H';
}
