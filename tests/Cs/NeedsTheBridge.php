<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cs;

use JesseGall\CodeCommandments\Cs\Bridge;

/**
 * A test that reads C# needs the Roslyn bridge, and the bridge needs its Docker image — where there is none,
 * the test is skipped rather than failed, naming the image and how it is built.
 */
trait NeedsTheBridge
{
    protected function requireTheBridge(): void
    {
        if (Bridge::located()->isNone()) {
            $this->markTestSkipped(Bridge::missing());
        }
    }
}
