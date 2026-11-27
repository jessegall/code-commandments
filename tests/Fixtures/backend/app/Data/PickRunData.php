<?php

namespace Shop\Data;

use JesseGall\CodeCommandments\Sins\Backend\MutableValueObject;
use JesseGall\CodeCommandments\Sins\Backend\Spatie\NonFinalData;

use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;
use Spatie\LaravelData\Data;

/**
 * The bins a pick run still has to visit, changed in place by the one picker that holds it.
 */
#[Righteous(NonFinalData::class)]
final class PickRunData extends Data
{
    /**
     * @param  list<string>  $openBins
     */
    public function __construct(
        public array $openBins = [],
    ) {}

    #[Righteous(MutableValueObject::class)]
    public function visit(string $bin): void
    {
        $this->openBins = array_values(array_diff($this->openBins, [$bin]));
    }

    public function isDone(): bool
    {
        return $this->openBins === [];
    }
}

/**
 * A picker that keeps its pick run to itself: only this private property ever holds it.
 */
final class Picker
{
    private readonly PickRunData $run;

    public function __construct()
    {
        $this->run = PickRunData::from(['openBins' => ['A1', 'B4']]);
    }

    public function pick(string $bin): bool
    {
        $this->run->visit($bin);

        return $this->run->isDone();
    }
}

/**
 * A tally held in a private property, but handed out by a getter: every caller shares the same object.
 */
#[Sinful(NonFinalData::class)]
final class ShelfTallyData extends Data
{
    public function __construct(
        public int $counted = 0,
    ) {}

    #[Sinful(MutableValueObject::class)]
    public function add(int $items): void
    {
        $this->counted += $items;
    }
}

/**
 * The stock taker that hands its tally out.
 */
final class StockTaker
{
    private readonly ShelfTallyData $tally;

    public function __construct()
    {
        $this->tally = ShelfTallyData::from(['counted' => 0]);
    }

    public function record(int $items): void
    {
        $this->tally->add($items);
    }

    public function tally(): ShelfTallyData
    {
        return $this->tally;
    }
}
