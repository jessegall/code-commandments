<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ast;

/**
 * What happens to a variable at one point in its journey — the shape of a single
 * {@see Interaction} in a {@see AstNode::trace()}.
 */
enum InteractionKind: string
{
    /** `$x = …` — the variable is (re)written here. */
    case Assigned = 'assigned';

    /** `f($x)` / `new C($x)` — passed into a call. */
    case Argument = 'argument';

    /** `$x->method()` — a method is called on it. */
    case MethodCall = 'method-call';

    /** `$x->prop` — a property is read off it. */
    case PropertyFetch = 'property-fetch';

    /** `$x === null` / `$x !== null` — compared against null. */
    case NullChecked = 'null-checked';

    /** `$x ?? …` — coalesced away. */
    case Coalesced = 'coalesced';

    /** `$x?->…` — reached through the null-safe operator. */
    case Nullsafe = 'nullsafe';

    /** `return $x` — handed back to the caller. */
    case Returned = 'returned';

    /** Any other read. */
    case Read = 'read';

    /**
     * Is this interaction a null guard — the variable being checked for, or
     * routed around, absence?
     */
    public function deNulls(): bool
    {
        return match ($this) {
            self::NullChecked, self::Coalesced, self::Nullsafe => true,
            default => false,
        };
    }
}
