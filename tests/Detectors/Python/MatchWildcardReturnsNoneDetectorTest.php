<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Python;

use JesseGall\CodeCommandments\Detectors\Python\MatchWildcardReturnsNoneDetector;
use JesseGall\CodeCommandments\Python\Detector;
use PHPUnit\Framework\TestCase;

final class MatchWildcardReturnsNoneDetectorTest extends TestCase
{
    use ProvesAPythonRule;
    use FlagsEachSnippetOnce;

    private const string STATUS = "from enum import StrEnum\n\nclass Status(StrEnum):\n    PENDING = 'pending'\n    PAID = 'paid'\n    LATE = 'late'\n\n";

    private function rule(): Detector
    {
        return new MatchWildcardReturnsNoneDetector();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function thisSin(): iterable
    {
        yield 'None for an unhandled member' => [self::STATUS . "def colour(s):\n    match s:\n        case Status.PENDING:\n            return 'amber'\n        case Status.PAID:\n            return 'green'\n        case _:\n            return None\n"];
        yield 'a bare return' => [self::STATUS . "def colour(s):\n    match s:\n        case Status.PAID:\n            return 'green'\n        case _:\n            return\n"];
        yield 'False with an or-pattern' => [self::STATUS . "def open(s):\n    match s:\n        case Status.PENDING | Status.LATE:\n            return True\n        case _:\n            return False\n"];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notThisSin(): iterable
    {
        yield 'the wildcard raises' => [self::STATUS . "def colour(s):\n    match s:\n        case Status.PAID:\n            return 'green'\n        case _:\n            raise Unknown(s)\n"];
        yield 'a real default' => [self::STATUS . "def colour(s):\n    match s:\n        case Status.PAID:\n            return 'green'\n        case _:\n            return 'grey'\n"];
        yield 'an open subject of strings' => ["def colour(s):\n    match s:\n        case 'paid':\n            return 'green'\n        case _:\n            return None\n"];
        yield 'handled arms already answer None' => [self::STATUS . "def colour(s):\n    match s:\n        case Status.PAID:\n            return None\n        case Status.LATE:\n            return 'red'\n        case _:\n            return None\n"];
        yield 'members of no enum' => ["def colour(s):\n    match s:\n        case Codes.A:\n            return 'x'\n        case _:\n            return None\n"];
    }
}
