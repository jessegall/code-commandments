<?php

namespace Illuminate\Support;

trait InteractsWithTime
{
    /** @return int */
    protected function secondsUntil($delay) {}
    /** @return int */
    protected function availableAt($delay = 0) {}
    /** @return \DateTimeInterface|int */
    protected function parseDateInterval($delay) {}
    /** @return int */
    protected function currentTime() {}
    /** @return string */
    protected function runTimeForHumans($startTime, $endTime = NULL) {}
}
