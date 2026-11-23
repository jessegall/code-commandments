<?php

namespace Shop\Reports;

use JesseGall\CodeCommandments\Sins\Backend\RedundantArrowReturnType;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * The tills whose last count is older than the days a depot allows, by the length of their codes.
 */
final class OverdueTills
{
    /**
     * @param  array<string, int>  $daysSinceCount
     */
    public function __construct(private readonly array $daysSinceCount) {}

    /**
     * @return array<string, int>
     */
    #[Sinful(RedundantArrowReturnType::class)]
    public function overdue(int $allowedDays): array
    {
        return array_filter($this->daysSinceCount, fn (int $days): bool => $days > $allowedDays);
    }

    /**
     * @return list<int>
     */
    #[Sinful(RedundantArrowReturnType::class)]
    public function codeLengths(): array
    {
        return array_map(static fn (string $code): int => strlen($code), array_keys($this->daysSinceCount));
    }
}
