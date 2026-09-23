<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\CSharp;

use JesseGall\CodeCommandments\Detectors\Catalog;
use JesseGall\CodeCommandments\Engine;
use JesseGall\CodeCommandments\Testing\EngineFixture;
use JesseGall\CodeCommandments\Testing\FixtureTestCase;
use JesseGall\CodeCommandments\Testing\ProvesMarkerCoverage;
use JesseGall\CodeCommandments\Tests\Cs\NeedsTheBridge;

/**
 * The C# self-checking fixture: every C# {@see Catalog} detector over the Shop project, checked against
 * its `// @sin` markers. The flow is the shared {@see FixtureTestCase}; the Roslyn bridge reads it.
 */
final class CSharpFixtureTest extends FixtureTestCase
{
    use NeedsTheBridge;
    use ProvesMarkerCoverage;

    protected function setUp(): void
    {
        if (Catalog::csharp() === []) {
            $this->markTestSkipped('no C# rule ships yet — the first one enrols this fixture');
        }

        $this->requireTheBridge();
    }

    protected function fixture(): EngineFixture
    {
        return Engine::CSharp->fixture(dirname(__DIR__, 2) . '/Fixtures/csharp', Catalog::csharp());
    }
}
