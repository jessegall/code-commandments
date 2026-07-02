<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Testing;

use JesseGall\CodeCommandments\Ast\Codebase as BackendCodebase;
use JesseGall\CodeCommandments\Bridge\Bridge;
use JesseGall\CodeCommandments\Bridge\ConsumesContracts;
use JesseGall\CodeCommandments\Frontend\Detector;
use JesseGall\CodeCommandments\Vue\Codebase;

/**
 * A frontend fixture: a directory of `.vue`/`.ts` checked with `@sin` markers and
 * file-scoped diversity scenarios. Parameterised by the path to scan and the frontend
 * detectors to verify — the package points it at its own Shop components and full
 * catalog; a consumer points it at its own directory and custom detectors.
 *
 * A fixture is full-stack: any `.php` under it (a `server/` folder of `Data` classes)
 * is scanned as a backend codebase so its shapes reach the frontend detectors as
 * {@see \JesseGall\CodeCommandments\Bridge\Contract}s over the {@see Bridge} — exactly
 * as `judge` wires the two engines in production.
 */
final class FrontendFixture implements Fixture
{
    /**
     * @param  list<Detector>  $detectors
     */
    public function __construct(
        private readonly string $path,
        private readonly array $detectors,
    ) {}

    public function markerResults(): array
    {
        return new CommentMarkerVerifier()->verify($this->codebase(), $this->detectors());
    }

    public function scenarios(): array
    {
        return new ComponentScenarioResolver()->resolve($this->codebase(), $this->detectors());
    }

    private function codebase(): Codebase
    {
        return Codebase::scan($this->path);
    }

    /**
     * The detectors with cross-engine contracts injected — the backend `Data` shapes
     * under the fixture, published to every {@see ConsumesContracts} detector.
     *
     * @return list<Detector>
     */
    private function detectors(): array
    {
        $contracts = Bridge::gather(BackendCodebase::scan($this->path), $this->codebase());

        foreach ($this->detectors as $detector) {
            if ($detector instanceof ConsumesContracts) {
                $detector->withContracts($contracts);
            }
        }

        return $this->detectors;
    }
}
