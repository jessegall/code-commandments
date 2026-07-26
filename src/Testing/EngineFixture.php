<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Testing;

use JesseGall\CodeCommandments\Config;
use JesseGall\CodeCommandments\Detector;

/**
 * What a fixture is before either engine parses a byte of it: a DIRECTORY, the detectors to prove
 * against it, and the directory's OWN `.commandments/config.php` — because a fixture directory is a
 * project. A rule inert until the project declares something (a layer map, a threshold) fires only
 * once declared, so the fixture declares it exactly as a consumer would and tunes the detectors
 * here, before anything is verified. The file is never scanned as fixture SOURCE: both engines
 * prune `.`-prefixed directories. A declaration aimed at a detector this fixture omits is ignored,
 * since a fixture legitimately holds a subset of the catalog.
 *
 * @see BackendFixture the PHP engine's fixture
 * @see FrontendFixture the Vue engine's — same mechanism, both engines
 */
abstract class EngineFixture implements Fixture
{
    /**
     * @var list<Detector>
     */
    protected readonly array $detectors;

    /**
     * @param  list<Detector>  $detectors
     */
    public function __construct(
        protected readonly string $path,
        array $detectors,
    ) {
        Config::load($path)->tune($detectors);

        $this->detectors = $detectors;
    }
}
