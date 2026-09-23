<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Python;

use JesseGall\CodeCommandments\Detectors\Python\MemberOutOfOrderDetector;
use JesseGall\CodeCommandments\Python\Detector;
use PHPUnit\Framework\TestCase;

final class MemberOutOfOrderDetectorTest extends TestCase
{
    use ProvesAPythonRule;
    use FlagsEachSnippetOnce;

    private function rule(): Detector
    {
        return new MemberOutOfOrderDetector();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function thisSin(): iterable
    {
        yield 'an upper-case constant under a field' => ["class Client:\n    timeout = 30\n    RETRIES = 3\n\n    def send(self):\n        pass\n"];
        yield 'a ClassVar under dataclass fields' => ["@dataclass\nclass Order:\n    number: str\n    lines: list\n    LIMIT: ClassVar[int] = 50\n"];
        yield 'a Final under an attribute' => ["class Shop:\n    name = 'shop'\n    CURRENCY: Final = 'EUR'\n"];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notThisSin(): iterable
    {
        yield 'constants first' => ["class Client:\n    RETRIES = 3\n    timeout = 30\n"];
        yield 'only constants' => ["class Limits:\n    MAX = 10\n    MIN = 1\n"];
        yield 'enum members' => ["from enum import Enum\n\nclass Colour(Enum):\n    _ignore_ = 'x'\n    RED = 1\n"];
        yield 'below a method, the other rule' => ["class Client:\n    timeout = 30\n\n    def send(self):\n        pass\n\n    RETRIES = 3\n"];
    }
}
