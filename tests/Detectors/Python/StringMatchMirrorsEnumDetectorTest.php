<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Python;

use JesseGall\CodeCommandments\Detectors\Python\StringMatchMirrorsEnumDetector;
use JesseGall\CodeCommandments\Python\Detector;
use PHPUnit\Framework\TestCase;

final class StringMatchMirrorsEnumDetectorTest extends TestCase
{
    use ProvesAPythonRule;
    use FlagsEachSnippetOnce;

    private const string STATUS = "from enum import StrEnum, IntEnum\n\nclass Status(StrEnum):\n    PENDING = 'pending'\n    PAID = 'paid'\n\nclass Size(IntEnum):\n    SMALL = 1\n    LARGE = 2\n\n";

    private function rule(): Detector
    {
        return new StringMatchMirrorsEnumDetector();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function thisSin(): iterable
    {
        yield 'a raw string subject' => [self::STATUS . "def badge(raw):\n    match raw:\n        case 'pending':\n            return 'amber'\n        case 'paid':\n            return 'green'\n"];
        yield 'an attribute holding the string' => [self::STATUS . "def badge(order):\n    match order.status:\n        case 'pending' | 'paid':\n            return 'known'\n        case _:\n            return 'odd'\n"];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notThisSin(): iterable
    {
        yield 'the .value read, the other rule' => [self::STATUS . "def badge(s):\n    match s.value:\n        case 'pending':\n            return 'amber'\n"];
        yield 'the members' => [self::STATUS . "def badge(s):\n    match s:\n        case Status.PENDING:\n            return 'amber'\n"];
        yield 'numbers' => [self::STATUS . "def box(n):\n    match n:\n        case 1:\n            return 'S'\n        case 2:\n            return 'L'\n"];
        yield 'strings no enum holds' => [self::STATUS . "def a(x):\n    match x:\n        case 'red':\n            return 1\n"];
    }
}
