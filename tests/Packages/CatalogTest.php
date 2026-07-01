<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Packages;

use JesseGall\CodeCommandments\Packages\Catalog;
use JesseGall\CodeCommandments\Packages\LaravelPackage;
use JesseGall\CodeCommandments\Packages\Package;
use PHPUnit\Framework\TestCase;

/**
 * The package registry is where a general detector reads cross-cutting facts (a framework
 * boundary) without naming the framework — so feature-envy exempts a request handler because the
 * LARAVEL package declared its request types a boundary, not because feature-envy knows Laravel.
 */
final class CatalogTest extends TestCase
{
    public function test_packages_auto_enrol_from_the_folder(): void
    {
        $this->assertContainsOnlyInstancesOf(Package::class, Catalog::all());
        $this->assertNotEmpty(array_filter(Catalog::all(), static fn (Package $p): bool => $p instanceof LaravelPackage));
    }

    public function test_boundary_types_aggregate_the_registered_packages_request_bases(): void
    {
        $boundaries = Catalog::boundaryTypes();

        $this->assertContains('Illuminate\\Http\\Request', $boundaries);
        $this->assertContains('Illuminate\\Foundation\\Http\\FormRequest', $boundaries);
        $this->assertContains('Laravel\\Mcp\\Request', $boundaries);
    }
}
