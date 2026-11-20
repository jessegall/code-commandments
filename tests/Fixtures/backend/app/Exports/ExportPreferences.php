<?php

namespace Shop\Exports;

use JesseGall\CodeCommandments\Sins\Backend\CapabilityProbeHub;
use JesseGall\CodeCommandments\Testing\Sinful;

#[Sinful(CapabilityProbeHub::class)]
final class ExportPreferences
{
    public function __construct(private readonly object $settings) {}

    public function canEmail(): bool
    {
        return $this->settings instanceof ExportSettings
            && $this->settings->enabled
            && $this->settings->email;
    }

    public function canArchive(): bool
    {
        return $this->settings instanceof ExportSettings && $this->settings->archive;
    }

    public function format(): string
    {
        return $this->settings instanceof ExportSettings ? $this->settings->format : 'csv';
    }
}
