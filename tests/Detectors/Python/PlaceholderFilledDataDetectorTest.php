<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Python;

use JesseGall\CodeCommandments\Detectors\Python\PlaceholderFilledDataDetector;
use JesseGall\CodeCommandments\Python\Detector;
use PHPUnit\Framework\TestCase;

final class PlaceholderFilledDataDetectorTest extends TestCase
{
    use ProvesAPythonRule;
    use FlagsEachSnippetOnce;

    private const string CARD = "from dataclasses import dataclass\n\n@dataclass(frozen=True)\nclass Card:\n    title: str\n    body: str\n    note: str | None = None\n    count: int = 0\n\n";

    private function rule(): Detector
    {
        return new PlaceholderFilledDataDetector();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function thisSin(): iterable
    {
        yield 'by keyword' => [self::CARD . "def card(order):\n    return Card(title=order.name, body='')\n"];
        yield 'by position' => [self::CARD . "def card(order):\n    return Card('', order.summary())\n"];
        yield 'double quotes' => [self::CARD . "def card(order):\n    return Card(order.name, \"\")\n"];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notThisSin(): iterable
    {
        yield 'a real value' => [self::CARD . "def card(order):\n    return Card(order.name, order.summary())\n"];
        yield 'an optional field' => [self::CARD . "def card(order):\n    return Card(order.name, order.body, note='')\n"];
        yield 'a zero' => [self::CARD . "def card(order):\n    return Card(order.name, order.body, count=0)\n"];
        yield 'not a dataclass' => ["class Card:\n    def __init__(self, title: str, body: str):\n        self.title, self.body = title, body\n\ndef card(order):\n    return Card(order.name, '')\n"];
        yield 'an unknown class' => ["def card(order):\n    return Other(order.name, '')\n"];
    }
}
