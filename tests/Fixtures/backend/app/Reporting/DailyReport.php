<?php

namespace Shop\Reporting;

use JesseGall\CodeCommandments\Sins\Backend\ArrayReturnBag;
use JesseGall\CodeCommandments\Testing\Fixed;

/**
 * What the string-keyed bag became. The keys a caller used to guess at are fields, so a typo is a
 * failure here rather than a null three layers down.
 */
#[Fixed(ArrayReturnBag::class)]
final class DailyReport
{
    public function __construct(
        public readonly int $gross,
        public readonly int $net,
    ) {}
}
