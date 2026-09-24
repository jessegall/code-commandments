<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\CSharp;

use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Detectors\CSharp\DanglingDocReferenceDetector;
use JesseGall\CodeCommandments\Tests\Cs\NeedsTheBridge;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A `cref` naming something the project does not declare is stale; one into a library, or into a namespace a
 * `using` the compilation cannot resolve, cannot be judged from here.
 */
final class DanglingDocReferenceDetectorTest extends TestCase
{
    use NeedsTheBridge;

    protected function setUp(): void
    {
        $this->requireTheBridge();
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function sources(): array
    {
        $order = "namespace Shop.Orders;\npublic sealed class Order\n{\n    public void Add(int line) { }\n    public void Add(string sku) { }\n}\n";

        return [
            'a type nothing declares' => ["{$order}/// <summary>Prices an <see cref=\"Basket\"/>.</summary>\npublic sealed class Pricer { }", 1],
            'a type gone from a namespace of its own' => ["{$order}/// <summary>Replaces <see cref=\"Shop.Orders.Cart\"/>.</summary>\npublic sealed class Pricer { }", 1],
            'a member gone from its own type' => ["{$order}/// <summary>Called by <see cref=\"Order.Remove\"/>.</summary>\npublic sealed class Pricer { }", 1],
            'a type that exists' => ["{$order}/// <summary>Prices an <see cref=\"Order\"/>.</summary>\npublic sealed class Pricer { }", 0],
            'an overloaded member' => ["{$order}/// <summary>Called by <see cref=\"Order.Add\"/>.</summary>\npublic sealed class Pricer { }", 0],
            'a member gone from a library type' => ["{$order}/// <summary>Like <see cref=\"System.String.Shout\"/>.</summary>\npublic sealed class Pricer { }", 0],
            'a file that misses a reference' => ["{$order}/// <summary>Marks a test run with <see cref=\"NotInParallelAttribute\"/>.</summary>\n[NotInParallel]\npublic sealed class PricerTests { }", 0],
            'a name a missing package may hold' => ["using Vendor.Payments;\n{$order}/// <summary>Charges through <see cref=\"PaymentClient\"/>.</summary>\npublic sealed class Pricer { }", 0],
        ];
    }

    #[DataProvider('sources')]
    public function test_flags_a_cref_to_a_name_the_project_does_not_declare(string $source, int $flagged): void
    {
        $this->assertCount($flagged, new DanglingDocReferenceDetector()->find(Codebase::fromString($source, 'Pricer.cs')), $source);
    }
}
