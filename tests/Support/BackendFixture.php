<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Support;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Catalog;
use JesseGall\CodeCommandments\Testing\ClassScenarioResolver;
use JesseGall\CodeCommandments\Testing\SinfulMarkerVerifier;

/**
 * The backend fixture: the Shop PHP app under `tests/Fixtures/backend`, checked with
 * `#[Sinful]` markers and class-scoped scenarios.
 */
final class BackendFixture implements Fixture
{
    public function name(): string
    {
        return 'backend';
    }

    public function markerResults(): array
    {
        return new SinfulMarkerVerifier()->verify($this->codebase(), Catalog::all());
    }

    public function scenarios(): array
    {
        return new ClassScenarioResolver()->resolve($this->codebase(), Catalog::all());
    }

    private function codebase(): Codebase
    {
        return Codebase::scan(__DIR__ . '/../Fixtures/backend');
    }
}
