<?php

namespace Shop\Reporting;

use JesseGall\CodeCommandments\Sins\Backend\RethrownUnchanged;
use JesseGall\CodeCommandments\Testing\Fixed;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Stamps an archived report with the day it covers. The catch below names a failure it does
 * nothing about, and the repair under it is the method with no clause at all: the failure
 * travels to the caller either way, and now the reader can see that it does.
 */
final class ArchiveStamp
{
    #[Sinful(RethrownUnchanged::class)]
    public function day(string $covered): string
    {
        try {
            return (new \DateTimeImmutable($covered))->format('Y-m-d');
        } catch (\Exception $e) {
            throw $e;
        }
    }

    #[Fixed(RethrownUnchanged::class)]
    public function dayCovered(string $covered): string
    {
        return (new \DateTimeImmutable($covered))->format('Y-m-d');
    }
}
