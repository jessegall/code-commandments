<?php

namespace Shop\Reporting;

use JesseGall\CodeCommandments\Sins\Backend\BlankStringDefault;
use JesseGall\CodeCommandments\Testing\Sinful;

final readonly class ReportTitle
{
    #[Sinful(BlankStringDefault::class)]
    public function __construct(public string $custom = '') {}
}
