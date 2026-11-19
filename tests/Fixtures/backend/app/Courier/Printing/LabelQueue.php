<?php

namespace Shop\Courier\Printing;

use JesseGall\CodeCommandments\Sins\Backend\ArrayBag;
use JesseGall\CodeCommandments\Sins\Backend\ArrayBagParameter;
use JesseGall\CodeCommandments\Testing\Fixed;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * The print jobs waiting for the label printer. `push` hands its job array on to `spool`, so the bag
 * travels two methods deep before anything reads it.
 */
final class LabelQueue
{
    /**
     * @var list<string>
     */
    private array $spooled = [];

    #[Sinful(ArrayBagParameter::class)]
    public function push(array $job): void
    {
        $this->spool($job);
    }

    #[Sinful(ArrayBagParameter::class)]
    #[Sinful(ArrayBag::class)]
    private function spool(array $job): void
    {
        $this->spooled[] = sprintf('%s x%d (%s)', $job['tracking'], $job['copies'], $job['format']);
    }

    #[Fixed(ArrayBagParameter::class)]
    public function pushJob(LabelJob $job): void
    {
        $this->spooled[] = $job->line();
    }
}

/**
 * One label to print: the parcel it belongs to, how many copies and the printer's format.
 */
#[Fixed(ArrayBagParameter::class)]
final readonly class LabelJob
{
    public function __construct(
        public string $tracking,
        public int $copies,
        public string $format,
    ) {}

    public function line(): string
    {
        return sprintf('%s x%d (%s)', $this->tracking, $this->copies, $this->format);
    }
}
