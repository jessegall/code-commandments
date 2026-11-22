<?php

namespace Shop\Fulfillment;

use Illuminate\Database\ConnectionInterface;
use JesseGall\CodeCommandments\Sins\Backend\Laravel\QueryInLoop;
use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Plans the pickers' rota. Every loop but the last turns a number of times the code itself fixes (a field written
 * once with a list, a constant list, three rows taken, a count to eight, a choice of two written lists), so its
 * queries are bounded; the last counts to a number the caller passes, one insert per turn.
 */
final class PickerRotaPlanning
{
    private const array SHIFTS = ['early', 'late', 'night'];

    /**
     * @var list<string>
     */
    private readonly array $docks;

    public function __construct(private readonly ConnectionInterface $database)
    {
        $this->docks = ['north', 'south'];
    }

    /**
     * @return list<string>
     */
    public function docks(): array
    {
        return $this->docks;
    }

    #[Righteous(QueryInLoop::class)]
    public function openDocks(): void
    {
        foreach ($this->docks() as $dock) {
            $this->database->table('docks')->insert(['name' => $dock]);
        }
    }

    #[Righteous(QueryInLoop::class)]
    public function openShifts(): void
    {
        foreach (self::SHIFTS as $shift) {
            $this->database->table('shifts')->insert(['name' => $shift]);
        }
    }

    #[Righteous(QueryInLoop::class)]
    public function trainNewest(): void
    {
        $pickerIds = $this->database->table('pickers')->orderByDesc('hired_on')->take(3)->pluck('id');
        foreach ($pickerIds as $pickerId) {
            $this->database->table('trainings')->insert(['picker' => $pickerId]);
        }
    }

    #[Righteous(QueryInLoop::class)]
    public function reserveBenches(): void
    {
        $bench = 0;
        while ($bench < 8) {
            $bench++;
            $this->database->table('benches')->insert(['number' => $bench]);
        }
    }

    #[Righteous(QueryInLoop::class)]
    public function openWaves(bool $weekend): void
    {
        foreach ($weekend ? ['morning', 'noon'] : ['morning'] as $wave) {
            $this->database->table('waves')->insert(['name' => $wave]);
        }
    }

    #[Sinful(QueryInLoop::class)]
    public function reserveLockers(int $lockers): void
    {
        $locker = 0;
        while ($locker < $lockers) {
            $locker++;
            $this->database->table('lockers')->insert(['number' => $locker]);
        }
    }
}
