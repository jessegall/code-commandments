<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Frontend;

use JesseGall\CodeCommandments\Detectors\Catalog;
use JesseGall\CodeCommandments\Testing\Fixture;
use JesseGall\CodeCommandments\Testing\FixtureTestCase;
use JesseGall\CodeCommandments\Testing\FrontendFixture;

/**
 * The frontend self-checking fixture: every frontend {@see Catalog} detector over the
 * Shop `.vue` components, checked against its `<!-- @sin -->` markers. The flow is the
 * shared {@see FixtureTestCase}; this only points it at the Shop components and catalog.
 */
final class FrontendFixtureTest extends FixtureTestCase
{
    protected function fixture(): Fixture
    {
        return new FrontendFixture(dirname(__DIR__, 2) . '/Fixtures/frontend', Catalog::frontend());
    }
}
