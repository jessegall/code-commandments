<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Fixtures\Backend\Sinful\PreferEnumCaseGroups;

class OneOff
{
    /**
     * A 3-case group that appears only here — a genuine one-off, NOT reused,
     * so it must not be flagged.
     *
     * @return list<CompareOperator>
     */
    public function textual(): array
    {
        return [CompareOperator::StartsWith, CompareOperator::Contains, CompareOperator::EndsWith];
    }
}
