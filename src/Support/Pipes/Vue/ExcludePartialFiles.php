<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Pipes\Vue;

use JesseGall\CodeCommandments\Support\Pipes\Pipe;

/**
 * Filter to exclude partial files (files in Partials/ directory).
 *
 * Returns null for partial files, signaling the pipeline to return righteous early.
 *
 * @implements Pipe<VueContext, VueContext|null>
 */
final class ExcludePartialFiles implements Pipe
{
    public function handle(mixed $input): mixed
    {
        if ($input->filePathContains('/Partials/') || $input->filePathContains('/partials/')) {
            return null;
        }

        return $input;
    }
}
