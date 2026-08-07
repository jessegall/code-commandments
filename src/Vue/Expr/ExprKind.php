<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue\Expr;

/**
 * What an {@see Expr} node IS — the closed set of shapes our JS-expression parser produces.
 *
 * A type rather than a tag string so that every `match` over it is exhaustive: adding a case here
 * makes each reader answer for it, instead of a `default` answering on their behalf.
 */
enum ExprKind: string
{
    case Identifier = 'identifier';

    case Literal = 'literal';

    /**
     * `a.b` / `a?.b`
     */
    case Member = 'member';

    /**
     * `a[b]`
     */
    case Index = 'index';

    /**
     * `f(...)`
     */
    case Call = 'call';

    /**
     * `!a`, `-a`
     */
    case Unary = 'unary';

    /**
     * `a === b`, `a || b`, `a + b`
     */
    case Binary = 'binary';

    case Conditional = 'conditional';

    case Array = 'array';

    case Object = 'object';

    case Arrow = 'arrow';

    /**
     * `v-for`: aliases (in|of) iterable
     */
    case For = 'for';

    /**
     * `target = value` — an event handler write.
     */
    case Assign = 'assign';

    case Unknown = 'unknown';
}
