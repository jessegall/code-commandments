<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Testing;

use JesseGall\CodeCommandments\Ast\Codebase as BackendCodebase;
use JesseGall\CodeCommandments\Bridge\Bridge;
use JesseGall\CodeCommandments\Bridge\ConsumesContracts;
use JesseGall\CodeCommandments\Frontend\Detector;
use JesseGall\CodeCommandments\Vue\Codebase;

/**
 * A frontend fixture with `@sin` markers on `.vue`/`.ts` components; full-stack with optional
 * `.php` backend shapes reaching frontend detectors via Bridge.
 *
 * @property-read list<Detector> $detectors
 */
final class FrontendFixture extends EngineFixture
{
    private ?Codebase $scanned = null;

    private ?BackendCodebase $server = null;


    public function markerResults(): array
    {
        return new CommentMarkerVerifier()->verify($this->codebase(), $this->detectors());
    }

    public function scenarios(): array
    {
        return new ComponentScenarioResolver()->resolve($this->codebase(), $this->detectors());
    }

    /**
     * Chain detection is backend-only (a {@see \JesseGall\CodeCommandments\Detectors\ChainDetector}
     * follows a PHP value through the PHP AST), so a frontend fixture has none.
     */
    public function chainSpans(): array
    {
        return [];
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
     * The detectors with cross-engine contracts injected — the backend `Data` shapes
     * under the fixture, published to every {@see ConsumesContracts} detector.
     *
     * @return list<Detector>
     */
    private function detectors(): array
    {
        $this->server ??= BackendCodebase::scan($this->path);

        Bridge::publish(Bridge::gather($this->server, $this->codebase()), $this->detectors);

        return $this->detectors;
    }
}
