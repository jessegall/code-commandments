<?php

namespace Shop\Dispatch;

/**
 * One beat of the dispatch floor's clock, with the second it struck at.
 */
final readonly class ShiftTick
{
    public function __construct(public int $at) {}
}
