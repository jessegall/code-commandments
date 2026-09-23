<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Python;

use JesseGall\CodeCommandments\Detectors\Python\CancelledFallbackDetector;
use JesseGall\CodeCommandments\Python\Detector;
use PHPUnit\Framework\TestCase;

final class CancelledFallbackDetectorTest extends TestCase
{
    use ProvesAPythonRule;
    use FlagsEachSnippetOnce;

    private function rule(): Detector
    {
        return new CancelledFallbackDetector();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function thisSin(): iterable
    {
        yield 'or-blank against the blank' => ["def a(order):\n    return (order.note or '') != ''\n"];
        yield 'a keyed zero against zero' => ["def a(row):\n    if row.get('qty', 0) == 0:\n        skip(row)\n"];
        yield 'the blank on the left' => ["def a(x):\n    return '' == (x.name or '')\n"];
        yield 'longhand' => ["def a(x):\n    return (x.code if x.code is not None else '') == ''\n"];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notThisSin(): iterable
    {
        yield 'None against None' => ["def a(row):\n    return row.get('k', None) is None\n"];
        yield 'an empty list' => ["def a(row):\n    return row.get('items', []) == []\n"];
        yield 'another value' => ["def a(x):\n    return (x.name or '') == 'admin'\n"];
        yield 'a real default' => ["def a(x):\n    return (x.currency or 'EUR') == 'EUR'\n"];
        yield 'not compared' => ["def a(x):\n    send(x.name or '')\n"];
    }
}
