<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Py\Expr;

/**
 * What a Python {@see Expr} is. A type rather than a tag string, so every `match` over it is exhaustive
 * and a kind added here is a kind every reader has to answer for.
 */
enum ExprKind: string
{
    case Name = 'name';

    /**
     * A value written in the source: a number, a string, bytes, True/False, None or `...`.
     */
    case Literal = 'literal';

    case FString = 'fstring';

    case Attribute = 'attribute';

    case Subscript = 'subscript';

    case Slice = 'slice';

    case Call = 'call';

    /**
     * `name=value` in a call's arguments.
     */
    case Keyword = 'keyword';

    /**
     * `*value` or `**value` — unpacking into a call, a display or a dict.
     */
    case Starred = 'starred';

    case Lambda = 'lambda';

    case Conditional = 'conditional';

    case Binary = 'binary';

    case Unary = 'unary';

    /**
     * A comparison chain — `a < b <= c`, `x is not None`, `k not in seen`.
     */
    case Compare = 'compare';

    case Walrus = 'walrus';

    case Tuple = 'tuple';

    case List = 'list';

    case Set = 'set';

    case Dict = 'dict';

    case Comprehension = 'comprehension';

    /**
     * One `for … in … if …` clause of a comprehension.
     */
    case ComprehensionFor = 'for';

    case Yield = 'yield';

    case Unknown = 'unknown';
}
