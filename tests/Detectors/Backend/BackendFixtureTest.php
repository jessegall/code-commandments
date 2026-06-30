<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Backend;

use JesseGall\CodeCommandments\Tests\Support\BackendFixture;
use JesseGall\CodeCommandments\Tests\Support\Fixture;
use JesseGall\CodeCommandments\Tests\Support\FixtureTestCase;

/**
 * The backend self-checking fixture: every {@see \JesseGall\CodeCommandments\Detectors\Catalog}
 * detector over the Shop PHP app, checked against its `#[Sinful]` markers. The flow
 * is the shared {@see FixtureTestCase}; this only names the engine.
 */
final class BackendFixtureTest extends FixtureTestCase
{
    protected function fixture(): Fixture
    {
        return new BackendFixture();
    }
}
