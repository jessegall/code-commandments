<?php

namespace Shop\Labels;

/** Records what was printed, for the reprint audit. */
final class PrintLog
{
    public function record(string $jobId): void {}
}
