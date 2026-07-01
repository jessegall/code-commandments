<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Packages;

use JesseGall\CodeCommandments\Ast\Codebase;
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

    public function test_contract_methods_expose_the_framework_hooks(): void
    {
        $codebase = Codebase::fromString(<<<'PHP'
            <?php
            namespace Illuminate\Foundation\Http { class FormRequest {} }
            namespace App { class StoreOrder extends \Illuminate\Foundation\Http\FormRequest {} }
            PHP);

        $this->assertContains('rules', Catalog::contractMethods()['Illuminate\\Foundation\\Http\\FormRequest'] ?? []);
        $this->assertTrue(Catalog::isContractMethod($codebase, 'App\\StoreOrder', 'rules'));
        $this->assertFalse(Catalog::isContractMethod($codebase, 'App\\StoreOrder', 'somethingElse'));
    }

    public function test_array_returning_types_and_no_container_types_are_declared(): void
    {
        $this->assertContains('Illuminate\\Foundation\\Http\\FormRequest', Catalog::arrayReturningTypes());
        $this->assertContains('Illuminate\\Contracts\\Database\\Eloquent\\CastsAttributes', Catalog::noContainerTypes());
    }
}
