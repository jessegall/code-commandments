<?php

namespace Shop\Diagnostics;

use JesseGall\CodeCommandments\Sins\Backend\MutableStaticState;
use JesseGall\CodeCommandments\Testing\Righteous;

/**
 * A probe suite shares one expensive fixture across its probes: the runner calls its static setup hook
 * once, before any probe runs, and the hook fills a static the probes only read — the order is the
 * runner's, not whoever wrote last.
 */
abstract class ProbeRunner
{
    public static function setUpBeforeProbes(): void {}
}

final class ProbeSuite extends ProbeRunner
{
    private static ?string $baseline = null;

    #[Righteous(MutableStaticState::class)]
    public static function setUpBeforeProbes(): void
    {
        self::$baseline = date('c');
    }

    public function baseline(): ?string
    {
        return self::$baseline;
    }
}
