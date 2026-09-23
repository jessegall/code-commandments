<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Python;

use JesseGall\CodeCommandments\Detectors\Python\DataClumpDetector;
use JesseGall\CodeCommandments\Py\Codebase;
use JesseGall\CodeCommandments\Py\NodeMatch;
use PHPUnit\Framework\TestCase;

final class DataClumpDetectorTest extends TestCase
{
    private const string SHIP = "(self, street: str, city: str, postcode: str, weight: float = 0.0)";

    public function test_flags_one_scalar_signature_threaded_through_two_classes(): void
    {
        $source = "class Labels:\n    def print" . self::SHIP . ":\n        pass\n\n\nclass Quotes:\n    def price(self, postcode: str, city: str, street: str, weight: float):\n        pass\n";

        $this->assertSame(['FunctionDef print', 'FunctionDef price'], $this->scopesIn($source));
    }

    public function test_leaves_one_class_two_scalars_and_untyped_params(): void
    {
        $this->assertSame([], $this->scopesIn("class A:\n    def a" . self::SHIP . ":\n        pass\n\n    def b" . self::SHIP . ":\n        pass\n"));
        $this->assertSame([], $this->scopesIn("def a(street: str, city: str):\n    pass\n\n\nclass B:\n    def b(self, street: str, city: str):\n        pass\n"));
        $this->assertSame([], $this->scopesIn("def a(street, city, postcode):\n    pass\n\n\nclass B:\n    def b(self, street, city, postcode):\n        pass\n"));
    }

    public function test_leaves_the_value_being_born(): void
    {
        $source = "class Address:\n    def __init__" . self::SHIP . ":\n        pass\n\n\nclass Parcel:\n    @classmethod\n    def of" . str_replace('self', 'cls', self::SHIP) . ":\n        return cls()\n";

        $this->assertSame([], $this->scopesIn($source));
    }

    public function test_an_override_repeats_its_parents_signature_and_is_no_clump(): void
    {
        $source = "class Controller:\n    def create" . self::SHIP . ":\n        pass\n\n\nclass Todos(Controller):\n    def create" . self::SHIP . ":\n        return super().create(street, city, postcode, weight)\n\n\nclass Works(Controller):\n    def create" . self::SHIP . ":\n        pass\n";

        $this->assertSame([], $this->scopesIn($source));
    }

    /**
     * @return list<string>
     */
    private function scopesIn(string $source): array
    {
        return array_map(static fn (NodeMatch $match): string => $match->scope(), new DataClumpDetector()->find(Codebase::fromString($source)));
    }
}
