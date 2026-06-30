<?php

namespace Shop\Reporting;

use JesseGall\CodeCommandments\Detectors\Backend\WrappingWithoutCauseDetector;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Exceptions\IntegrationException;

/**
 * Uploads a finished export, rewrapping storage failures into a domain exception
 * without keeping the underlying cause.
 */
final class ExportUploader
{
    #[Sinful(WrappingWithoutCauseDetector::class)]
    public function upload(string $path): void
    {
        try {
            $this->pushToBucket($path);
        } catch (\Throwable $storageError) {
            throw new IntegrationException($path);
        }
    }

    private function pushToBucket(string $path): void {}

    public function archivePath(string $name, int $year): string
    {
        return "archive/{$year}/{$name}.zip";
    }
}
