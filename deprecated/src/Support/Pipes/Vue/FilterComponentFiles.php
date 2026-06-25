<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Pipes\Vue;

use JesseGall\CodeCommandments\Support\Pipes\Pipe;

/**
 * Filter to only include component files (files in Components/ directory).
 *
 * Returns null for non-component files, signaling the pipeline to return righteous early.
 *
 * @implements Pipe<VueContext, VueContext|null>
 */
final class FilterComponentFiles implements Pipe
{
    public function handle(mixed $input): mixed
    {
        if (!$input->filePathContains('/Components/') && !$input->filePathContains('/components/')) {
            return null;
        }

        return $input;
    }
}
