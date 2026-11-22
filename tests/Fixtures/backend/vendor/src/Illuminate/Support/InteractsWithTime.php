<?php

namespace Illuminate\Support;

trait InteractsWithTime
{
    protected function secondsUntil($delay) {}
    protected function availableAt($delay = 0) {}
    protected function parseDateInterval($delay) {}
    protected function currentTime() {}
    protected function runTimeForHumans($startTime, $endTime = NULL) {}
}
