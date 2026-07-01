<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Packages;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Packages\Catalog;
use JesseGall\CodeCommandments\Packages\Clause;
use JesseGall\CodeCommandments\Packages\Exemptions;
use JesseGall\CodeCommandments\Packages\LaravelPackage;
use JesseGall\CodeCommandments\Packages\Package;
use JesseGall\CodeCommandments\Packages\Tags\ArrayReturning;
use JesseGall\CodeCommandments\Packages\Tags\Boundary;
use JesseGall\CodeCommandments\Packages\Tags\ContractMethod;
use JesseGall\CodeCommandments\Packages\Tags\NoContainer;
use PHPUnit\Framework\TestCase;

/**
 * The open exemption registry — a package tags its framework's types, a detector reads the tag, and
 * neither names the other. The clause matching is the crux, so it's tested directly; the built-in
 * LaravelPackage is checked end-to-end through the static {@see Exemptions::has}.
 */
final class ExemptionsTest extends TestCase
{
    public function test_packages_auto_enrol_from_the_folder(): void
    {
        $this->assertContainsOnlyInstancesOf(Package::class, Catalog::all());
        $this->assertNotEmpty(array_filter(Catalog::all(), static fn (Package $p): bool => $p instanceof LaravelPackage));
    }

    public function test_a_clause_exempts_whole_classes_and_specific_methods_and_global_methods(): void
    {
        $codebase = Codebase::fromString(<<<'PHP'
            <?php
            namespace Vendor { class Base {} class Config {} }
            namespace App {
                class Handler extends \Vendor\Base {}
                class Settings extends \Vendor\Config {}
                class Plain {}
            }
            PHP);

        $clause = new Clause()
            ->classes('Vendor\\Base')                 // whole class (any method)
            ->on('Vendor\\Config', 'schema')          // only schema() on a Config
            ->methods('__invoke');                    // ignored everywhere

        // whole-class: a Base subclass is exempt for any method (or none)…
        $this->assertTrue($clause->matches($codebase, 'App\\Handler'));
        $this->assertTrue($clause->matches($codebase, 'App\\Handler', 'anything'));
        // method-scoped: only schema() on a Config subclass…
        $this->assertTrue($clause->matches($codebase, 'App\\Settings', 'schema'));
        $this->assertFalse($clause->matches($codebase, 'App\\Settings', 'other'));
        // global method: any class…
        $this->assertTrue($clause->matches($codebase, 'App\\Plain', '__invoke'));
        // and a plain class with a plain method is NOT exempt.
        $this->assertFalse($clause->matches($codebase, 'App\\Plain', 'run'));
    }

    public function test_the_laravel_package_registers_its_tags(): void
    {
        $codebase = Codebase::fromString(<<<'PHP'
            <?php
            namespace Illuminate\Foundation\Http { class FormRequest {} }
            namespace Illuminate\Contracts\Database\Eloquent { interface CastsAttributes {} }
            namespace App {
                class StoreOrder extends \Illuminate\Foundation\Http\FormRequest {}
                class MoneyCast implements \Illuminate\Contracts\Database\Eloquent\CastsAttributes {}
            }
            PHP);

        $this->assertTrue(Exemptions::has(Boundary::class, $codebase, 'App\\StoreOrder'));
        $this->assertTrue(Exemptions::has(ArrayReturning::class, $codebase, 'App\\StoreOrder'));
        $this->assertTrue(Exemptions::has(ContractMethod::class, $codebase, 'App\\StoreOrder', 'rules'));
        $this->assertFalse(Exemptions::has(ContractMethod::class, $codebase, 'App\\StoreOrder', 'somethingElse'));
        $this->assertTrue(Exemptions::has(NoContainer::class, $codebase, 'App\\MoneyCast'));
    }

    public function test_an_unregistered_tag_exempts_nothing(): void
    {
        $codebase = Codebase::fromString('<?php class Foo {}');

        $this->assertFalse(Exemptions::has(self::class, $codebase, 'Foo'));
    }

    public function test_a_consumer_package_registered_via_config_joins_the_registry(): void
    {
        $codebase = Codebase::fromString('<?php namespace App { class Widget {} }');

        // Before registering: the consumer's own tag/class is unknown.
        $this->assertFalse(Exemptions::has(self::class, $codebase, 'App\\Widget'));

        Exemptions::usePackages(ConsumerPackage::class);

        // Now its exemption is live — the same path the CLI takes from Config::package().
        $this->assertTrue(Exemptions::has(ExemptionsTest::class, $codebase, 'App\\Widget'));
    }

    protected function tearDown(): void
    {
        // usePackages() sets a static; reset it so a consumer package can't leak into other tests.
        Exemptions::usePackages();
    }
}

/** A consumer's own package — registers an exemption under this test's class as the tag. */
final class ConsumerPackage extends Package
{
    public function register(Exemptions $exemptions): void
    {
        $exemptions->exempt(ExemptionsTest::class)->classes('App\\Widget');
    }
}
