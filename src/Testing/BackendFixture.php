<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Testing;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Backend\Detector;

/**
 * A backend fixture: a directory of PHP checked with `#[Sinful]` markers and
 * class-scoped diversity scenarios. Parameterised by the path to scan and the
 * backend detectors to verify — the package points it at its own Shop app and full
 * catalog; a consumer points it at its own directory and custom detectors.
 */
final class BackendFixture implements Fixture
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
        return new SinfulMarkerVerifier()->verify($this->codebase(), $this->detectors);
    }

    public function scenarios(): array
    {
        return new ClassScenarioResolver()->resolve($this->codebase(), $this->detectors);
    }

    private function codebase(): Codebase
    {
        return Codebase::scan($this->path);
    }
}
