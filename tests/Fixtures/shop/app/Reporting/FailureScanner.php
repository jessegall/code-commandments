<?php

namespace Shop\Reporting;

use JesseGall\CodeCommandments\Detectors\Backend\FeatureEnvyDetector;
use JesseGall\CodeCommandments\Testing\Sinful;

final class FailureScanner
{
    #[Sinful(FeatureEnvyDetector::class)]
    public function containsFailure(LogLine $line): bool
    {
        if ($line->level === 'error') {
            return true;
        }

        foreach ($line->children as $child) {
            if ($this->containsFailure($child)) {
                return true;
            }
        }

        return false;
    }
}
