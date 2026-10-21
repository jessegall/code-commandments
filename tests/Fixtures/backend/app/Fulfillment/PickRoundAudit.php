<?php

namespace Shop\Fulfillment;

use JesseGall\CodeCommandments\Sins\Backend\Laravel\QueryInLoop;
use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Models\Picker;

/**
 * Says who picked each round. The first method finds the picker inside the loop, one query per
 * round; the one below it asks for all of them once and then reads what is already in memory,
 * which is the same answer at one query instead of a hundred.
 */
final class PickRoundAudit
{
    /**
     * @param array<int, int> $pickerIds
     *
     * @return array<int, string>
     */
    #[Sinful(QueryInLoop::class)]
    public function namesOf(array $pickerIds): array
    {
        $names = [];
        foreach ($pickerIds as $pickerId) {
            $picker = Picker::find($pickerId);
            $names[] = $picker === null ? 'unknown' : $picker->name;
        }

        return $names;
    }

    /**
     * @param array<int, int> $pickerIds
     *
     * @return array<int, string>
     */
    #[Righteous(QueryInLoop::class)]
    public function namesOfAll(array $pickerIds): array
    {
        $pickers = Picker::findMany($pickerIds)->keyBy('id');
        $names = [];
        foreach ($pickerIds as $pickerId) {
            $names[] = $pickers->get($pickerId)?->name ?? 'unknown';
        }

        return $names;
    }
}
