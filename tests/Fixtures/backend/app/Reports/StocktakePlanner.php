<?php

namespace Shop\Reports;

use DateTimeImmutable;
use JesseGall\CodeCommandments\Sins\Backend\Laravel\DuplicatedConfigDefault;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Plans the warehouse stocktake rota. The cycle length is read with its own fallback while
 * `config/stocktake.php` already declares 14, so the schedule silently depends on which of the two
 * copies happens to be authoritative at the moment the key is absent.
 */
final class StocktakePlanner
{
    /** @var list<DateTimeImmutable> */
    private array $planned = [];

    #[Sinful(DuplicatedConfigDefault::class)]
    public function plan(DateTimeImmutable $from, int $cycles): array
    {
        $days = $this->readInt('stocktake.cycle_days', 14);
        $cursor = $from;

        for ($i = 0; $i < $cycles; $i++) {
            $cursor = $cursor->modify("+{$days} days");
            $this->planned[] = $cursor;
        }

        return $this->planned;
    }

    private function readInt(string $key, int $fallback): int
    {
        return $fallback;
    }
}
