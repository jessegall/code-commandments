<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Testing;

/**
 * A detector that carries its own self-checking fixture. Implement this on a custom
 * detector to point the {@see FixtureTestCase} at the directory of marked example
 * files that prove it — `#[Sinful(self::class)]` on the PHP it must flag (backend),
 * or `<!-- @sin ShortName -->` on the `.vue` it must flag (frontend). The package's
 * own detectors share one big fixture app instead, so they don't implement this;
 * a detector living in YOUR codebase declares where its own fixture lives.
 */
interface HasFixture
{
    /**
     * The directory holding this detector's marked fixture files. Several detectors
     * may return the same path to share one fixture directory.
     */
    public function fixturePath(): string;
}
