<?php

namespace Shop\Dispatch;

/**
 * Every run a depot has sent out, newest first.
 */
final class DepotRunLog
{
    /**
     * @var list<DepotRun>
     */
    private array $runs = [];

    public function record(DepotRun $run): void
    {
        array_unshift($this->runs, $run);
    }

    public function newest(): DepotRun
    {
        return $this->runs[0];
    }

    public function sent(): int
    {
        return count($this->runs);
    }
}
