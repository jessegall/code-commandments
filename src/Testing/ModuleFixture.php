<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Testing;

use Closure;
use JesseGall\CodeCommandments\Detector;
use JesseGall\CodeCommandments\Language;
use JesseGall\CodeCommandments\ModuleCodebase;

/**
 * The self-checking fixture of an engine that reads its language as parsed modules — Python's, C#'s:
 * every detector over the fixture's sources, checked against the `@sin Name` comments above what it must
 * flag, in the language's own comment syntax. Scenarios and recurrence spans are read by the resolvers
 * every engine uses; these engines have no chain engine, so they have no chain spans.
 */
final class ModuleFixture extends EngineFixture
{
    private ?ModuleCodebase $scanned = null;

    /**
     * @param  list<Detector>  $detectors
     * @param  Closure(string): ModuleCodebase  $scan  how the engine reads the fixture's folder
     */
    public function __construct(string $path, array $detectors, private readonly Closure $scan, private readonly Language $language)
    {
        parent::__construct($path, $detectors);
    }

    public function markerResults(): array
    {
        return new ModuleMarkerVerifier()->verify($this->codebase(), $this->detectors);
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
        return ModuleFixtureExamples::extract($this->codebase(), $this->detectors, $this->language);
    }

    protected function markedNames(string $tag): array
    {
        return array_fill_keys(array_keys(DeclarationMarkers::inModules($this->codebase(), $tag)), true);
    }

    private function codebase(): ModuleCodebase
    {
        return $this->scanned ??= ($this->scan)($this->path);
    }
}
