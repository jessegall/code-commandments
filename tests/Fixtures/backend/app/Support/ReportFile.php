<?php

namespace Shop\Support;

use JesseGall\CodeCommandments\Sins\Backend\MemberOutOfOrder;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * A plain on-disk report file — NOT an Eloquent model, even though it has a
 * save(). Mutating-then-saving one is just building a file, not the model-at-the-
 * call-site sin.
 */
#[Sinful(MemberOutOfOrder::class)]
final class ReportFile
{
    public string $name = '';

    public string $contents = '';

    private const string EXTENSION = '.csv';

    public function save(): void
    {
        // write to disk
    }
}
