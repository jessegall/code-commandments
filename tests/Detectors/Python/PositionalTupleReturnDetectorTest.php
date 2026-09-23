<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Python;

use JesseGall\CodeCommandments\Detectors\Python\PositionalTupleReturnDetector;
use JesseGall\CodeCommandments\Python\Detector;
use PHPUnit\Framework\TestCase;

final class PositionalTupleReturnDetectorTest extends TestCase
{
    use ProvesAPythonRule;
    use FlagsEachSnippetOnce;

    private function rule(): Detector
    {
        return new PositionalTupleReturnDetector();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function thisSin(): iterable
    {
        yield 'bare' => ["def split(order, rates):\n    return order.net, rates.vat(order), order.currency\n"];
        yield 'parenthesised' => ["def parse(raw):\n    name, _, rest = raw.partition(':')\n    return (name, rest, len(raw))\n"];
        yield 'typed as a fixed tuple' => ["def totals(basket, fee) -> tuple[int, int, int]:\n    return basket.sum(), fee, basket.sum() + fee\n"];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notThisSin(): iterable
    {
        yield 'two elements' => ["def pair(a, b):\n    return a, b\n"];
        yield 'one source' => ["def parts(p):\n    return p.x, p.y, p.z\n"];
        yield 'a declared sequence' => ["def ids(a, b, c) -> tuple[int, ...]:\n    return a.id, b.id, c.id\n"];
        yield 'a list annotation' => ["def ids(a, b, c) -> list[int]:\n    return [a.id, b.id, c.id]\n"];
        yield 'a pickling protocol' => ["class Job:\n    def __reduce__(self):\n        return Job, (self.name,), self.state\n"];
        yield 'constants' => ["def version():\n    return 1, 2, 3\n"];
    }
}
