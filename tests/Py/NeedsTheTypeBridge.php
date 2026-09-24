<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Py;

use JesseGall\CodeCommandments\Py\TypeBridge;

/**
 * A test that reads Python's types needs the mypy bridge, and the bridge needs a `python3` — where there is
 * none, the test is skipped rather than failed.
 */
trait NeedsTheTypeBridge
{
    protected function requireTheTypeBridge(): void
    {
        if (TypeBridge::located()->isNone()) {
            $this->markTestSkipped('there is no python3, so there is no mypy bridge to type Python with');
        }
    }
}
