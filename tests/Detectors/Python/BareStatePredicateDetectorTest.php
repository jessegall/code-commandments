<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Python;

use JesseGall\CodeCommandments\Detectors\Python\BareStatePredicateDetector;
use JesseGall\CodeCommandments\Python\Detector;
use PHPUnit\Framework\TestCase;

final class BareStatePredicateDetectorTest extends TestCase
{
    use ProvesAPythonRule;
    use FlagsEachSnippetOnce;

    private function rule(): Detector
    {
        return new BareStatePredicateDetector();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function thisSin(): iterable
    {
        yield 'a method' => ["class Node:\n    def binds(self) -> bool:\n        return self.target is not None\n"];
        yield 'a property' => ["class Wheel:\n    @property\n    def spins(self) -> bool:\n        return self.speed > 0\n"];
        yield 'a snake_case compound' => ["class Job:\n    def waits_forever(self) -> bool:\n        return self.timeout is None\n"];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notThisSin(): iterable
    {
        yield 'a question' => ["class Node:\n    def is_bound(self) -> bool:\n        return self.target is not None\n"];
        yield 'a relation with an argument' => ["class Basket:\n    def contains(self, item) -> bool:\n        return item in self.items\n"];
        yield 'not a bool' => ["class Node:\n    def binds(self) -> list:\n        return self.bindings\n"];
        yield 'a plural noun' => ["class Form:\n    def fields(self) -> bool:\n        return bool(self._fields)\n"];
        yield 'a dunder' => ["class Box:\n    def __contains__(self, item) -> bool:\n        return False\n"];
        yield 'an override' => ["class Base:\n    def binds(self) -> bool:\n        return False\n\nclass Node(Base):\n    def binds(self) -> bool:\n        return True\n"];
    }
}
