<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Python;

use JesseGall\CodeCommandments\Detectors\Python\MemberAfterMethodDetector;
use JesseGall\CodeCommandments\Python\Detector;
use PHPUnit\Framework\TestCase;

final class MemberAfterMethodDetectorTest extends TestCase
{
    use ProvesAPythonRule;
    use FlagsEachSnippetOnce;

    private function rule(): Detector
    {
        return new MemberAfterMethodDetector();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function thisSin(): iterable
    {
        yield 'a constant under a method' => ["class Client:\n    def send(self):\n        return self.RETRIES\n\n    RETRIES = 3\n"];
        yield 'a dataclass field under a method' => ["@dataclass\nclass Order:\n    number: str\n\n    def total(self):\n        return 0\n\n    note: str = ''\n"];
        yield 'a class attribute between methods' => ["class Cache:\n    def __init__(self):\n        self.items = {}\n\n    hits = 0\n\n    def get(self, key):\n        return self.items[key]\n"];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notThisSin(): iterable
    {
        yield 'state at the top' => ["class Client:\n    RETRIES = 3\n\n    def send(self):\n        return self.RETRIES\n"];
        yield 'a property built from its methods' => ["class Box:\n    def _get_size(self):\n        return self._size\n\n    size = property(_get_size)\n"];
        yield 'a method wrapped after it' => ["class Tool:\n    def _make(cls):\n        return cls()\n\n    make = classmethod(_make)\n"];
        yield 'a protocol bound by assignment' => ["class Key:\n    def __eq__(self, other):\n        return self.v == other.v\n\n    __hash__ = None\n"];
        yield 'enum members after __new__' => ["from enum import IntEnum\n\nclass Status(IntEnum):\n    def __new__(cls, value, phrase):\n        obj = int.__new__(cls, value)\n        obj.phrase = phrase\n        return obj\n\n    OK = 200, 'OK'\n    CREATED = 201, 'Created'\n"];
        yield 'no methods' => ["class Point:\n    x = 0\n    y = 0\n"];
    }
}
