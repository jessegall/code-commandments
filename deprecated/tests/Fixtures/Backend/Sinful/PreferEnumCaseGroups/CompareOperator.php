<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Fixtures\Backend\Sinful\PreferEnumCaseGroups;

enum CompareOperator
{
    case Equals;
    case NotEquals;
    case GreaterThan;
    case GreaterOrEqual;
    case LessThan;
    case LessOrEqual;
    case StartsWith;
    case Contains;
    case EndsWith;

    /**
     * A named group living on the enum itself. The same three cases are
     * inlined elsewhere, but here — in the enum's own file — they must
     * NEVER be flagged: this is exactly where the accessor belongs.
     *
     * @return list<self>
     */
    public static function numeric(): array
    {
        return [self::Equals, self::NotEquals, self::GreaterThan];
    }
}
