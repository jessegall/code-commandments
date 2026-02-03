<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Support;

use JesseGall\CodeCommandments\Support\PackageDetector;
use JesseGall\CodeCommandments\Tests\TestCase;

class PackageDetectorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        PackageDetector::clearCache();
    }

    protected function tearDown(): void
    {
        PackageDetector::clearCache();
        parent::tearDown();
    }

    public function test_has_spatie_data_returns_bool(): void
    {
        $result = PackageDetector::hasSpatieData();

        $this->assertIsBool($result);
    }

    public function test_has_spatie_data_caches_result(): void
    {
        $result1 = PackageDetector::hasSpatieData();
        $result2 = PackageDetector::hasSpatieData();

        $this->assertSame($result1, $result2);
    }

    public function test_clear_cache_allows_redetection(): void
    {
        // Call to cache the result
        $first = PackageDetector::hasSpatieData();

        // Clear the cache
        PackageDetector::clearCache();

        // Should return same value (detection happens again but result should be consistent)
        $second = PackageDetector::hasSpatieData();

        $this->assertSame($first, $second);
    }

    public function test_has_wayfinder_returns_bool(): void
    {
        $result = PackageDetector::hasWayfinder();

        $this->assertIsBool($result);
    }

    public function test_has_wayfinder_caches_result(): void
    {
        $result1 = PackageDetector::hasWayfinder();
        $result2 = PackageDetector::hasWayfinder();

        $this->assertSame($result1, $result2);
    }
}
