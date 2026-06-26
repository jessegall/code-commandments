<?php

namespace Shop\Reporting;

use Shop\Support\ReportFile;

/**
 * Righteous twin for ModelMutationAtCallSite: set-then-save, but on a plain
 * ReportFile (not a Model), so it must NOT be flagged.
 */
final class ReportArchiver
{
    public function archive(ReportFile $file): void
    {
        $file->name = 'archived-' . $file->name;
        $file->contents = '';
        $file->save();
    }
}
