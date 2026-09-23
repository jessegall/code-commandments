<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Python;

use JesseGall\CodeCommandments\Detectors\Python\InLiteralsMirrorsEnumDetector;
use JesseGall\CodeCommandments\Python\Detector;
use PHPUnit\Framework\TestCase;

final class InLiteralsMirrorsEnumDetectorTest extends TestCase
{
    use ProvesAPythonRule;
    use FlagsEachSnippetOnce;

    private const string STATUS = "from enum import StrEnum, IntEnum\n\nclass Status(StrEnum):\n    PENDING = 'pending'\n    PAID = 'paid'\n    LATE = 'late'\n\nclass Size(IntEnum):\n    SMALL = 1\n    LARGE = 2\n\n";

    private function rule(): Detector
    {
        return new InLiteralsMirrorsEnumDetector();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function thisSin(): iterable
    {
        yield 'a tuple of strings' => [self::STATUS . "def open(order):\n    return order.status in ('pending', 'late')\n"];
        yield 'not in a list' => [self::STATUS . "def settled(s):\n    return s not in ['pending', 'late']\n"];
        yield 'a set of strings' => [self::STATUS . "def known(s):\n    if s in {'pending', 'paid', 'late'}:\n        keep(s)\n"];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notThisSin(): iterable
    {
        yield 'the members themselves' => [self::STATUS . "def open(s):\n    return s in (Status.PENDING, Status.LATE)\n"];
        yield 'values no enum holds' => [self::STATUS . "def primary(c):\n    return c in ('red', 'blue')\n"];
        yield 'numbers, which coincide with an int enum by chance' => [self::STATUS . "def boxed(size):\n    return size in (1, 2)\n"];
        yield 'one literal' => [self::STATUS . "def paid(s):\n    return s in ('paid',)\n"];
        yield 'mixed with a non-literal' => [self::STATUS . "def a(s, other):\n    return s in ('paid', other)\n"];
        yield 'a string contained in a string' => [self::STATUS . "def a(s):\n    return 'paid' in s\n"];
    }
}
