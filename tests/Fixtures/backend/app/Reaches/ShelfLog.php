<?php

namespace Shop\Reaches;

use JesseGall\CodeCommandments\Testing\Righteous;
use Psr\Log\LoggerInterface;

final class ShelfLog
{
    public function __construct(private LoggerInterface $logger) {}

    #[Righteous(RepeatedReach::class)]
    public function restocked(Shelf $shelf, Builder $query): Builder
    {
        $this->logger->info('restocked');
        $this->logger->warning($shelf->label());
        $shelf->items->push('first');
        $shelf->items->push('second');

        return $query->where('shelf', $shelf->label())->where('stocked', true);
    }

    #[Righteous(RepeatedReach::class)]
    public function owner(Shelf $shelf): string
    {
        return $shelf->aisle->owner();
    }
}
