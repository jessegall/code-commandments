<?php

namespace Shop\Tills;

use JesseGall\CodeCommandments\Sins\Backend\RedundantArrowReturnType;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * The line a till prints at the end of the day: its sales in cents, the codes it rang and its state.
 */
final class TillTotals
{
    /**
     * @param  list<int>  $sales
     * @param  list<int>  $codes
     */
    #[Sinful(RedundantArrowReturnType::class)]
    public function printed(array $sales, array $codes, TillState $state): string
    {
        $doubled = array_map(static fn (int $cents): int => $cents * 2, $sales);
        $labels = array_map(static fn (int $code): string => (string) $code, $codes);
        $stateName = static fn (): string => $state->value;

        return array_sum($doubled) . ' ' . implode(',', $labels) . ' ' . $stateName();
    }
}
