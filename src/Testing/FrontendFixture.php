<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Testing;

use JesseGall\CodeCommandments\Frontend\Detector;
use JesseGall\CodeCommandments\Vue\Codebase;

/**
 * A frontend fixture: a directory of `.vue` checked with `<!-- @sin -->` markers and
 * component-scoped diversity scenarios. Parameterised by the path to scan and the
 * frontend detectors to verify — the package points it at its own Shop components
 * and full catalog; a consumer points it at its own directory and custom detectors.
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
        return new CommentMarkerVerifier()->verify($this->codebase(), $this->detectors);
    }

    public function scenarios(): array
    {
        return new ComponentScenarioResolver()->resolve($this->codebase(), $this->detectors);
    }

    private function codebase(): Codebase
    {
        return Codebase::scan($this->path);
    }
}
