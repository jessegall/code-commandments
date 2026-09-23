<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Python;

use JesseGall\CodeCommandments\Detectors\Python\TypeSwitchDetector;
use JesseGall\CodeCommandments\Python\Detector;
use PHPUnit\Framework\TestCase;

final class TypeSwitchDetectorTest extends TestCase
{
    use ProvesAPythonRule;
    use FlagsEachSnippetOnce;

    private const string SHAPES = "class Shape:\n    pass\n\n\nclass Circle(Shape):\n    radius = 1\n\n\nclass Square(Shape):\n    side = 1\n\n\n";

    private function rule(): Detector
    {
        return new TypeSwitchDetector();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function thisSin(): iterable
    {
        yield 'an if/elif ladder' => [self::SHAPES . "def area(shape: Shape) -> float:\n    if isinstance(shape, Circle):\n        return 3.14 * shape.radius ** 2\n    elif isinstance(shape, Square):\n        return shape.side ** 2\n    return 0.0\n"];
        yield 'sequential ifs that return' => [self::SHAPES . "def describe(shape: Shape) -> str:\n    if isinstance(shape, Circle):\n        return f'circle {shape.radius}'\n    if isinstance(shape, Square):\n        return f'square {shape.side}'\n    return 'shape'\n"];
        yield 'a method switching on its argument' => [self::SHAPES . "class Painter:\n    def paint(self, shape: Shape) -> None:\n        if isinstance(shape, Circle):\n            self.arc(shape.radius)\n        elif isinstance(shape, Square):\n            self.box(shape.side)\n"];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notThisSin(): iterable
    {
        yield 'one type test' => [self::SHAPES . "def area(shape: Shape) -> float:\n    if isinstance(shape, Circle):\n        return 3.14 * shape.radius ** 2\n    return 0.0\n"];
        yield 'one membership question' => [self::SHAPES . "def round_(shape: Shape) -> bool:\n    if isinstance(shape, (Circle, Square)):\n        return True\n    return False\n"];
        yield 'types the codebase does not own' => ["def size(value) -> int:\n    if isinstance(value, str):\n        return len(value)\n    elif isinstance(value, dict):\n        return len(value.keys())\n    return 0\n"];
        yield 'different subjects' => [self::SHAPES . "def pair(a: Shape, b: Shape) -> str:\n    if isinstance(a, Circle):\n        return 'a'\n    if isinstance(b, Square):\n        return 'b'\n    return ''\n"];
        yield 'an operator protocol method' => [self::SHAPES . "class Area:\n    def __sub__(self, other):\n        if isinstance(other, Circle):\n            return self.minus(other.radius)\n        if isinstance(other, Square):\n            return self.minus(other.side)\n        return NotImplemented\n"];
        yield 'sequential ifs that only normalise the value' => [self::SHAPES . "def unwrap(shape: Shape) -> Shape:\n    if isinstance(shape, Circle):\n        shape = shape.inner\n    if isinstance(shape, Square):\n        shape = shape.inner\n    return shape\n"];
        yield 'a named constructor' => [self::SHAPES . "class Drawing:\n    @classmethod\n    def of(cls, shape: Shape) -> 'Drawing':\n        if isinstance(shape, Circle):\n            return cls()\n        elif isinstance(shape, Square):\n            return cls()\n        return cls()\n"];
        yield 'a mapper translating every arm' => [self::SHAPES . "def to_wire(shape: Shape) -> dict:\n    if isinstance(shape, Circle):\n        return circle_payload(shape)\n    elif isinstance(shape, Square):\n        return square_payload(shape)\n    return base_payload(shape)\n"];
    }
}
