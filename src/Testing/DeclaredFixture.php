<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Testing;

use InvalidArgumentException;
use JesseGall\CodeCommandments\Backend\Detector as BackendDetector;
use JesseGall\CodeCommandments\Frontend\Detector as FrontendDetector;

/**
 * The consumer's fixture: hand it your custom detectors and it verifies each against
 * the directory that detector declares via {@see HasFixture}. Detectors that share a
 * path are checked together (one scan, markers route by `#[Sinful(...)]` / `@sin`),
 * so you can keep all your fixtures in one folder or split them per detector. Backend
 * and frontend detectors are routed to their own engine automatically.
 */
final class DeclaredFixture implements Fixture
{
    /** @var list<Fixture> */
    private readonly array $fixtures;

    /**
     * @param  list<BackendDetector|FrontendDetector>  $detectors
     */
    public function __construct(array $detectors)
    {
        $this->fixtures = self::group($detectors);
    }

    public function markerResults(): array
    {
        return array_merge(...array_map(static fn (Fixture $f): array => $f->markerResults(), $this->fixtures));
    }

    public function scenarios(): array
    {
        return array_merge(...array_map(static fn (Fixture $f): array => $f->scenarios(), $this->fixtures));
    }

    /**
     * Bucket the detectors by engine and declared path, then build one engine
     * fixture per bucket.
     *
     * @param  list<BackendDetector|FrontendDetector>  $detectors
     * @return list<Fixture>
     */
    private static function group(array $detectors): array
    {
        $backend = [];
        $frontend = [];

        foreach ($detectors as $detector) {
            if (! $detector instanceof HasFixture) {
                throw new InvalidArgumentException($detector::class . ' must implement ' . HasFixture::class . ' to be verified against its own fixture.');
            }

            match (true) {
                $detector instanceof BackendDetector => $backend[$detector->fixturePath()][] = $detector,
                $detector instanceof FrontendDetector => $frontend[$detector->fixturePath()][] = $detector,
            };
        }

        $fixtures = [];

        foreach ($backend as $path => $group) {
            $fixtures[] = new BackendFixture($path, $group);
        }

        foreach ($frontend as $path => $group) {
            $fixtures[] = new FrontendFixture($path, $group);
        }

        return $fixtures;
    }
}
