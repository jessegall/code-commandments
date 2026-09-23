<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Python;

use JesseGall\CodeCommandments\Detectors\Python\NestedConditionalDetector;
use JesseGall\CodeCommandments\Python\Detector;
use PHPUnit\Framework\TestCase;

final class NestedConditionalDetectorTest extends TestCase
{
    use ProvesAPythonRule;
    use FlagsEachSnippetOnce;

    private function rule(): Detector
    {
        return new NestedConditionalDetector();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function thisSin(): iterable
    {
        yield 'chained in the else' => ["label = 'high' if score > 8 else 'mid' if score > 4 else 'low'\n"];
        yield 'nested in the then' => ["rate = (0.2 if vip else 0.1) if member else 0\n"];
        yield 'three deep counts once' => ["size = 'xl' if n > 9 else 'l' if n > 6 else 'm' if n > 3 else 's'\n"];
        yield 'buried in a call in a branch' => ["def a(order):\n    return ship(order) if order.paid else hold(order, 'late' if order.overdue else 'new')\n"];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notThisSin(): iterable
    {
        yield 'one conditional' => ["label = 'paid' if order.paid else 'open'\n"];
        yield 'two side by side' => ["pair = ('a' if x else 'b', 'c' if y else 'd')\n"];
        yield 'a conditional as the test' => ["mode = 'r' if (a if b else c) else 'w'\n"];
        yield 'a comprehension filter' => ["kept = [r for r in rows if r.ok]\n"];
    }
}
