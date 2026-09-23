<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Python;

use JesseGall\CodeCommandments\Detectors\Catalog;
use JesseGall\CodeCommandments\Testing\Fixture;
use JesseGall\CodeCommandments\Testing\FixtureTestCase;
use JesseGall\CodeCommandments\Testing\PythonFixture;

/**
 * The Python self-checking fixture: every Python {@see Catalog} detector over the Shop's `.py` sources,
 * checked against its `# @sin` markers. The flow is the shared {@see FixtureTestCase}.
 */
final class PythonFixtureTest extends FixtureTestCase
{
    protected function setUp(): void
    {
        if (Catalog::python() === []) {
            $this->markTestSkipped('no Python rule ships yet — the first one enrols this fixture');
        }
    }

    protected function fixture(): Fixture
    {
        return new PythonFixture(dirname(__DIR__, 2) . '/Fixtures/python', Catalog::python());
    }
}
