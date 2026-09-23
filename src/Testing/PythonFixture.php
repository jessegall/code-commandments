<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Testing;

use JesseGall\CodeCommandments\Py\Codebase;

/**
 * The Python self-checking fixture: every Python detector over the fixture's `.py` sources, checked
 * against the `# @sin Name` comments above what it must flag. Scenarios and recurrence spans are read
 * by the same resolvers the other engines use; Python has no chain engine, so it has no chain spans.
 */
final class PythonFixture extends EngineFixture
{
    private ?Codebase $scanned = null;

    public function markerResults(): array
    {
        return new PythonMarkerVerifier()->verify($this->codebase(), $this->detectors);
    }

    public function scenarios(): array
    {
        return new ComponentScenarioResolver()->resolve($this->codebase(), $this->detectors);
    }

    public function chainSpans(): array
    {
        return [];
    }

    public function recurrenceSpans(): array
    {
        return new RecurrenceSpanResolver()->resolve($this->codebase(), $this->detectors);
    }

    public function examples(): array
    {
        return PythonFixtureExamples::extract($this->codebase(), $this->detectors);
    }

    private function codebase(): Codebase
    {
        return $this->scanned ??= Codebase::scan($this->path);
    }
}
