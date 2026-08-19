<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Testing;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Bridge\Bridge;
use JesseGall\CodeCommandments\Vue\Codebase as FrontendCodebase;

/**
 * A backend fixture: a directory of PHP checked with `#[Sinful]` markers and
 * class-scoped diversity scenarios. Parameterised by the path to scan and the
 * backend detectors to verify — the package points it at its own Shop app and full
 * catalog; a consumer points it at its own directory and custom detectors.
 *
 * @property-read list<Detector> $detectors
 */
final class BackendFixture extends EngineFixture
{
    private ?Codebase $scanned = null;

    private ?FrontendCodebase $components = null;

    public function markerResults(): array
    {
        return new SinfulMarkerVerifier()->verify($this->codebase(), $this->detectors());
    }

    public function scenarios(): array
    {
        return new ClassScenarioResolver()->resolve($this->codebase(), $this->detectors());
    }

    public function chainSpans(): array
    {
        return new ChainSpanResolver()->resolve($this->codebase(), $this->detectors());
    }

    public function recurrenceSpans(): array
    {
        return new RecurrenceSpanResolver()->resolve($this->codebase(), $this->detectors());
    }

    private function codebase(): Codebase
    {
        return $this->scanned ??= Codebase::scan($this->path);
    }

    /**
     * The detectors with cross-engine contracts injected — the twin of
     * {@see FrontendFixture::detectors}, so a backend rule that asks the frontend a question is
     * proven against the `.ts`/`.vue` files sitting under the same fixture.
     *
     * @return list<Detector>
     */
    private function detectors(): array
    {
        $this->components ??= FrontendCodebase::scan($this->path);

        Bridge::publish(Bridge::gather($this->codebase(), $this->components), $this->detectors);

        return $this->detectors;
    }
}
