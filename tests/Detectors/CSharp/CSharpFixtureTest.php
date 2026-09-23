<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\CSharp;

use JesseGall\CodeCommandments\Cs\Bridge;
use JesseGall\CodeCommandments\Detectors\Catalog;
use JesseGall\CodeCommandments\Engine;
use JesseGall\CodeCommandments\Testing\EngineFixture;
use JesseGall\CodeCommandments\Testing\FixtureTestCase;
use JesseGall\CodeCommandments\Testing\ProvesMarkerCoverage;

/**
 * The C# self-checking fixture: every C# {@see Catalog} detector over the Shop project, checked against
 * its `// @sin` markers. The flow is the shared {@see FixtureTestCase}; the Roslyn bridge reads it.
 */
final class CSharpFixtureTest extends FixtureTestCase
{
    use ProvesMarkerCoverage;

    protected function setUp(): void
    {
        if (Catalog::csharp() === []) {
            $this->markTestSkipped('no C# rule ships yet — the first one enrols this fixture');
        }

        if (Bridge::located()->isNone()) {
            $this->markTestSkipped('the .NET SDK is not installed, so there is no bridge to read C# with');
        }
    }

    protected function fixture(): EngineFixture
    {
        return Engine::CSharp->fixture(dirname(__DIR__, 2) . '/Fixtures/csharp', Catalog::csharp());
    }
}
