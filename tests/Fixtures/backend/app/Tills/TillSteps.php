<?php

namespace Shop\Tills;

/**
 * The steps the till runs, as the shop wired them.
 */
final class TillSteps
{
    /**
     * @var list<class-string<TillStep>>
     */
    private array $steps = [];

    /**
     * @param  list<class-string<TillStep>>  $steps
     */
    public function addAll(array $steps): void
    {
        $this->steps = [...$this->steps, ...$steps];
    }

    public function count(): int
    {
        return count($this->steps);
    }
}
