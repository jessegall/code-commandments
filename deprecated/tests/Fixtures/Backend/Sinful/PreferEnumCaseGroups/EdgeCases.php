<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Fixtures\Backend\Sinful\PreferEnumCaseGroups;

class EdgeCases
{
    /**
     * A 2-case array — below the min_group threshold, never flagged. This
     * pair is duplicated in twoCaseAgain() so it would qualify on reuse,
     * but the size gate excludes it first.
     *
     * @return list<CompareOperator>
     */
    public function twoCase(): array
    {
        return [CompareOperator::Equals, CompareOperator::NotEquals];
    }

    /**
     * @return list<CompareOperator>
     */
    public function twoCaseAgain(): array
    {
        return [CompareOperator::Equals, CompareOperator::NotEquals];
    }

    /**
     * A 3-item array mixing two different enums — not a single named group,
     * never flagged. Duplicated below so reuse alone won't trip it.
     *
     * @return list<CompareOperator|OtherEnum>
     */
    public function mixed(): array
    {
        return [CompareOperator::Equals, OtherEnum::Alpha, OtherEnum::Beta];
    }

    /**
     * @return list<CompareOperator|OtherEnum>
     */
    public function mixedAgain(): array
    {
        return [CompareOperator::Equals, OtherEnum::Alpha, OtherEnum::Beta];
    }

    /**
     * The numeric group used as an `in_array` haystack — belongs to the
     * CompareSelf equalsAny rule, so this rule must NOT flag it (and it must
     * not inflate the group's reuse count either).
     */
    public function isNumeric(CompareOperator $op): bool
    {
        return in_array($op, [CompareOperator::Equals, CompareOperator::NotEquals, CompareOperator::GreaterThan], true);
    }
}
