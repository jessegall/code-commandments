<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Python;

use JesseGall\CodeCommandments\Detectors\Python\HandRolledReplaceDetector;
use JesseGall\CodeCommandments\Python\Detector;
use PHPUnit\Framework\TestCase;

final class HandRolledReplaceDetectorTest extends TestCase
{
    use ProvesAPythonRule;
    use FlagsEachSnippetOnce;

    private const string ORDER = "from dataclasses import dataclass\n\n@dataclass(frozen=True)\nclass Order:\n    number: str\n    lines: tuple\n    note: str\n    status: str\n\n";

    private function rule(): Detector
    {
        return new HandRolledReplaceDetector();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function thisSin(): iterable
    {
        yield 'positional' => [self::ORDER . "    def paid(self):\n        return Order(self.number, self.lines, self.note, 'paid')\n"];
        yield 'keywords' => [self::ORDER . "    def noted(self, note):\n        return Order(number=self.number, lines=self.lines, note=note, status=self.status)\n"];
        yield 'through type(self)' => [self::ORDER . "    def renumbered(self, number):\n        return type(self)(number, self.lines, self.note, self.status)\n"];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notThisSin(): iterable
    {
        yield 'replace already' => ["from dataclasses import dataclass, replace\n\n@dataclass(frozen=True)\nclass Order:\n    number: str\n    status: str\n\n    def paid(self):\n        return replace(self, status='paid')\n"];
        yield 'a copy, nothing changed' => [self::ORDER . "    def copy(self):\n        return Order(self.number, self.lines, self.note, self.status)\n"];
        yield 'too few carried' => [self::ORDER . "    def reset(self, number, lines):\n        return Order(number, lines, self.note, 'new')\n"];
        yield 'not a dataclass' => ["class Order:\n    def __init__(self, a, b, c, d):\n        self.a, self.b, self.c, self.d = a, b, c, d\n\n    def with_d(self, d):\n        return Order(self.a, self.b, self.c, d)\n"];
        yield 'another class built' => [self::ORDER . "    def receipt(self):\n        return Receipt(self.number, self.lines, self.note, 'x')\n"];
    }
}
