<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Support;

use JesseGall\CodeCommandments\Detectors\Catalog;
use JesseGall\CodeCommandments\Testing\CommentMarkerVerifier;
use JesseGall\CodeCommandments\Testing\ComponentScenarioResolver;
use JesseGall\CodeCommandments\Vue\Codebase;
use JesseGall\CodeCommandments\Frontend\Detector;

/**
 * The frontend fixture: the Shop `.vue` components under `tests/Fixtures/frontend`,
 * checked with `<!-- @sin -->` markers and component-scoped scenarios.
 */
final class FrontendFixture implements Fixture
{
    public function name(): string
    {
        return 'frontend';
    }

    public function markerResults(): array
    {
        return new CommentMarkerVerifier()->verify($this->codebase(), $this->detectors());
    }

    public function scenarios(): array
    {
        return new ComponentScenarioResolver()->resolve($this->codebase(), $this->detectors());
    }

    /**
     * @return list<Detector>
     */
    private function detectors(): array
    {
        return Catalog::frontend();
    }

    private function codebase(): Codebase
    {
        return Codebase::scan(__DIR__ . '/../Fixtures/frontend');
    }
}
