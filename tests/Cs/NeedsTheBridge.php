<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cs;

use JesseGall\CodeCommandments\Cs\Bridge;

/**
 * A test that reads C# needs the Roslyn bridge, and the bridge needs the .NET SDK — where there is none,
 * the test is skipped rather than failed.
 */
trait NeedsTheBridge
{
    protected function requireTheBridge(): void
    {
        if (Bridge::located()->isNone()) {
            $this->markTestSkipped('the .NET SDK is not installed, so there is no bridge to read C# with');
        }
    }
}
