<?php

namespace Shop\Support;

/**
 * A plain on-disk report file — NOT an Eloquent model, even though it has a
 * save(). Mutating-then-saving one is just building a file, not the model-at-the-
 * call-site sin.
 */
final class ReportFile
{
    public string $name = '';

    public string $contents = '';

    public function save(): void
    {
        // write to disk
    }
}
