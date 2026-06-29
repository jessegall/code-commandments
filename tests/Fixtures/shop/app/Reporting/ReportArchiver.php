<?php

namespace Shop\Reporting;

use JesseGall\CodeCommandments\Detectors\Backend\FeatureEnvyDetector;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Support\ReportFile;

/**
 * Righteous twin for ModelMutationAtCallSite (set-then-save on a plain ReportFile,
 * NOT a Model — so THAT detector stays silent), but a genuine FeatureEnvy sin: the
 * read-then-mutate belongs ON ReportFile as `$file->archive()`.
 */
final class ReportArchiver
{
    #[Sinful(FeatureEnvyDetector::class)]
    public function archive(ReportFile $file): void
    {
        $file->name = 'archived-' . $file->name;
        $file->contents = '';
        $file->save();
    }
}
