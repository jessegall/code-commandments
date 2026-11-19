<?php

namespace Shop\Courier\Printing;

/**
 * Reprints a parcel's label: once by building the job as a local array, once as a typed job.
 */
final class LabelReprints
{
    public function __construct(private readonly LabelQueue $queue) {}

    public function reprint(string $tracking): void
    {
        $job = ['tracking' => $tracking, 'copies' => 2, 'format' => 'zpl'];
        $this->queue->push($job);
    }

    public function reprintJob(string $tracking): void
    {
        $this->queue->pushJob(new LabelJob($tracking, 2, 'zpl'));
    }
}
