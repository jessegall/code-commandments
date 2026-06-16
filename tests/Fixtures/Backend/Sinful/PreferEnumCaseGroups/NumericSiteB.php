<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Fixtures\Backend\Sinful\PreferEnumCaseGroups;

class NumericSiteB
{
    /**
     * Same three cases as NumericSiteA, but written in a different ORDER —
     * canonicalisation must collapse them to the same group key.
     *
     * @return list<CompareOperator>
     */
    public function operators(): array
    {
        return [CompareOperator::GreaterThan, CompareOperator::Equals, CompareOperator::NotEquals];
    }
}
