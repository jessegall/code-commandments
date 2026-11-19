<?php

namespace Shop\Fulfillment;

use Illuminate\Database\ConnectionInterface;
use JesseGall\CodeCommandments\Sins\Backend\Laravel\QueryInLoop;
use JesseGall\CodeCommandments\Testing\Righteous;
use Shop\Models\Picker;

/**
 * Closes each picker's shift in a transaction of its own, so one picker's failure never rolls back another's. The
 * locking read belongs to that picker's transaction: batching it out of the loop would merge the transactions.
 */
final class PickerShiftClosing
{
    public function __construct(private readonly ConnectionInterface $connection) {}

    /**
     * @param  list<int>  $pickerIds
     */
    #[Righteous(QueryInLoop::class)]
    public function close(array $pickerIds): void
    {
        foreach ($pickerIds as $pickerId) {
            $this->connection->transaction(function () use ($pickerId): void {
                Picker::query()->lockForUpdate()->findOrFail($pickerId)->endShift();
            });
        }
    }

    #[Righteous(QueryInLoop::class)]
    public function closeAll(): void
    {
        foreach (Picker::query()->where('on_shift', true)->get() as $picker) {
            $picker->endShift();
        }
    }
}
