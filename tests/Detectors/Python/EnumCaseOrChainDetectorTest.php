<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Python;

use JesseGall\CodeCommandments\Detectors\Python\EnumCaseOrChainDetector;
use JesseGall\CodeCommandments\Python\Detector;
use PHPUnit\Framework\TestCase;

final class EnumCaseOrChainDetectorTest extends TestCase
{
    use ProvesAPythonRule;
    use FlagsEachSnippetOnce;

    private const string STATUS = "from enum import Enum, StrEnum\n\nclass Status(StrEnum):\n    PENDING = 'pending'\n    PAID = 'paid'\n    LATE = 'late'\n\nclass Urgent(Status):\n    pass\n\n";

    private function rule(): Detector
    {
        return new EnumCaseOrChainDetector();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function thisSin(): iterable
    {
        yield 'two cases with ==' => [self::STATUS . "def open(order):\n    return order.status == Status.PENDING or order.status == Status.LATE\n"];
        yield 'three cases with is' => [self::STATUS . "def open(s):\n    return s is Status.PENDING or s is Status.PAID or s is Status.LATE\n"];
        yield 'the case on the left of an attribute' => [self::STATUS . "def open(order):\n    return Status.PENDING == order.status or Status.LATE == order.status\n"];
        yield 'the case on the left' => [self::STATUS . "def open(s):\n    if Status.PENDING == s or Status.LATE == s:\n        chase(s)\n"];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notThisSin(): iterable
    {
        yield 'one case' => [self::STATUS . "def paid(s):\n    return s == Status.PAID\n"];
        yield 'two subjects' => [self::STATUS . "def a(x, y):\n    return x == Status.PAID or y == Status.PAID\n"];
        yield 'not an enum' => ["class Codes:\n    A = 'a'\n    B = 'b'\n\ndef a(x):\n    return x == Codes.A or x == Codes.B\n"];
        yield 'another test in the chain' => [self::STATUS . "def a(s, rush):\n    return s == Status.PENDING or rush\n"];
        yield 'two enums mixed' => [self::STATUS . "class Kind(Enum):\n    BOX = 1\n\ndef a(s):\n    return s == Status.PAID or s == Kind.BOX\n"];
    }
}
