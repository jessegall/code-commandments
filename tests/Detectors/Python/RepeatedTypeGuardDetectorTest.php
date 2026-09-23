<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Python;

use JesseGall\CodeCommandments\Detectors\Python\RepeatedTypeGuardDetector;
use JesseGall\CodeCommandments\Python\Detector;
use PHPUnit\Framework\TestCase;

final class RepeatedTypeGuardDetectorTest extends TestCase
{
    use ProvesARecurringPythonRule;

    private function rule(): Detector
    {
        return new RepeatedTypeGuardDetector();
    }

    /**
     * @return iterable<string, array{string, list<int>}>
     */
    public static function recurring(): iterable
    {
        yield 'a two-step narrowing in two functions' => ["def a(node):\n    if isinstance(node, Call) and isinstance(node.func, Attribute):\n        visit(node)\n\n\ndef b(node):\n    return isinstance(node, Call) and isinstance(node.func, Attribute)\n", [2, 7]];
        yield 'the same narrowing with a condition beside it' => ["def a(node):\n    return isinstance(node, Call) and isinstance(node.func, Name) and node.func.id == 'print'\n\n\ndef b(node):\n    return isinstance(node, Call) and isinstance(node.func, Name) and node.func.id == 'print'\n", [2, 6]];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notThisSin(): iterable
    {
        yield 'written once' => ["def a(node):\n    return isinstance(node, Call) and isinstance(node.func, Attribute)\n"];
        yield 'one isinstance only' => ["def a(node):\n    return isinstance(node, Call) and node.args\n\n\ndef b(node):\n    return isinstance(node, Call) and node.args\n"];
        yield 'different narrowings' => ["def a(node):\n    return isinstance(node, Call) and isinstance(node.func, Attribute)\n\n\ndef b(node):\n    return isinstance(node, Call) and isinstance(node.func, Name)\n"];
    }
}
