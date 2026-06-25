<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Fixtures\Backend\Sinful\PreferEnumCaseGroups;

class NumericSiteA
{
    /**
     * @return list<CompareOperator>
     */
    public function operators(): array
    {
        return [CompareOperator::Equals, CompareOperator::NotEquals, CompareOperator::GreaterThan];
    }
}
