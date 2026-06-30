<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Frontend;

use JesseGall\CodeCommandments\Tests\Support\FrontendFixture;
use JesseGall\CodeCommandments\Tests\Support\Fixture;
use JesseGall\CodeCommandments\Tests\Support\FixtureTestCase;

/**
 * The frontend self-checking fixture: every `Detectors\Frontend` detector over the
 * Shop `.vue` components, checked against its `<!-- @sin -->` markers. The flow is the
 * shared {@see FixtureTestCase}; this only names the engine.
 */
final class FrontendFixtureTest extends FixtureTestCase
{
    protected function fixture(): Fixture
    {
        return new FrontendFixture();
    }
}
