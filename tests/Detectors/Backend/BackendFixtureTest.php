<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Backend;

use JesseGall\CodeCommandments\Detectors\Catalog;
use JesseGall\CodeCommandments\Testing\BackendFixture;
use JesseGall\CodeCommandments\Testing\Fixture;
use JesseGall\CodeCommandments\Testing\FixtureTestCase;

/**
 * The backend self-checking fixture: every {@see Catalog} detector over the Shop PHP
 * app, checked against its `#[Sinful]` markers. The flow is the shared
 * {@see FixtureTestCase}; this only points it at the Shop app and full catalog.
 */
final class BackendFixtureTest extends FixtureTestCase
{
    protected function fixture(): Fixture
    {
        return new BackendFixture(dirname(__DIR__, 2) . '/Fixtures/backend', Catalog::backend());
    }
}
