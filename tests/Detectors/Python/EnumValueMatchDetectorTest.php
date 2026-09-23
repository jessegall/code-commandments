<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Python;

use JesseGall\CodeCommandments\Detectors\Python\EnumValueMatchDetector;
use JesseGall\CodeCommandments\Python\Detector;
use PHPUnit\Framework\TestCase;

final class EnumValueMatchDetectorTest extends TestCase
{
    use ProvesAPythonRule;
    use FlagsEachSnippetOnce;

    private const string STATUS = "from enum import StrEnum, IntEnum\n\nclass Status(StrEnum):\n    PENDING = 'pending'\n    PAID = 'paid'\n\nclass Size(IntEnum):\n    SMALL = 1\n    LARGE = 2\n\n";

    private function rule(): Detector
    {
        return new EnumValueMatchDetector();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function thisSin(): iterable
    {
        yield 'string values' => [self::STATUS . "def badge(order):\n    match order.status.value:\n        case 'pending':\n            return 'amber'\n        case 'paid':\n            return 'green'\n"];
        yield 'numbers with a wildcard' => [self::STATUS . "def box(size):\n    match size.value:\n        case 1:\n            return 'S'\n        case _:\n            return 'L'\n"];
        yield 'an or-pattern' => [self::STATUS . "def open(s):\n    match s.value:\n        case 'pending' | 'paid':\n            return True\n"];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notThisSin(): iterable
    {
        yield 'matching the members' => [self::STATUS . "def badge(s):\n    match s:\n        case Status.PENDING:\n            return 'amber'\n        case Status.PAID:\n            return 'green'\n"];
        yield 'inside the enum' => ["from enum import StrEnum\n\nclass Status(StrEnum):\n    PENDING = 'pending'\n    PAID = 'paid'\n\n    def badge(self):\n        match self.value:\n            case 'pending':\n                return 'amber'\n            case _:\n                return 'green'\n"];
        yield 'values no enum holds' => [self::STATUS . "def a(x):\n    match x.value:\n        case 'red':\n            return 1\n        case 'blue':\n            return 2\n"];
        yield 'not a value read' => [self::STATUS . "def a(x):\n    match x.kind:\n        case 'pending':\n            return 1\n"];
    }
}
