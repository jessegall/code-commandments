<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\CSharp;

use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Detectors\CSharp\DataClumpDetector;
use JesseGall\CodeCommandments\Tests\Cs\NeedsTheBridge;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Three or more value parameters, the same by type and name, declared by members of two types are a
 * clump; one type's members, a signature of two, constructors, factories building their own type, and
 * implementations repeating an interface's signature are not.
 */
final class DataClumpDetectorTest extends TestCase
{
    use NeedsTheBridge;

    protected function setUp(): void
    {
        $this->requireTheBridge();
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function types(): array
    {
        return [
            'two types threading street, city and postcode' => ['public class Labels { public string Print(string street, string city, string postcode) => street; } public class Quotes { public decimal Price(string postcode, string city, string street) => 1m; }', 2],
            'one type only' => ['public class Labels { public string Print(string street, string city, string postcode) => street; public string Again(string street, string city, string postcode) => city; }', 0],
            'two values' => ['public class Labels { public string Print(string street, string city) => street; } public class Quotes { public decimal Price(string street, string city) => 1m; }', 0],
            'constructors' => ['public class Labels { public Labels(string street, string city, string postcode) {} } public class Quotes { public Quotes(string street, string city, string postcode) {} }', 0],
            'factories building their own type' => ['public sealed record Place(string Street, string City, string Postcode) { public static Place Of(string street, string city, string postcode) => new(street, city, postcode); } public sealed record Stop(string Street, string City, string Postcode) { public static Stop Of(string street, string city, string postcode) => new(street, city, postcode); }', 0],
            'implementations of one interface' => ['public interface IAddressed { string Print(string street, string city, string postcode); } public class Labels : IAddressed { public string Print(string street, string city, string postcode) => street; } public class Quotes : IAddressed { public string Print(string street, string city, string postcode) => city; }', 0],
        ];
    }

    #[DataProvider('types')]
    public function test_flags_a_clump_of_values_threaded_through_two_types(string $types, int $flagged): void
    {
        $this->assertCount($flagged, new DataClumpDetector()->find(Codebase::fromString($types, 'Places.cs')), $types);
    }
}
