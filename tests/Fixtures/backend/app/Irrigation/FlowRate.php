<?php

namespace Shop\Irrigation;

final readonly class FlowRate
{
    public function __construct(public int $litresPerMinute) {}

    public function minutesFor(int $litres): int
    {
        return intdiv($litres + $this->litresPerMinute - 1, $this->litresPerMinute);
    }
}
