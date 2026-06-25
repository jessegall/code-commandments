<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Pipes\Vue;

use JesseGall\CodeCommandments\Support\Pipes\Pipe;

/**
 * Filter to only include page files (files in Pages/ directory).
 *
 * Returns null for non-page files, signaling the pipeline to return righteous early.
 *
 * @implements Pipe<VueContext, VueContext|null>
 */
final class FilterPageFiles implements Pipe
{
    public function handle(mixed $input): mixed
    {
        if (!$input->filePathContains('/Pages/') && !$input->filePathContains('/pages/')) {
            return null;
        }

        return $input;
    }
}
