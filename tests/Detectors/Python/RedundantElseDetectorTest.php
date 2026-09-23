<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Python;

use JesseGall\CodeCommandments\Detectors\Python\RedundantElseDetector;
use JesseGall\CodeCommandments\Python\Detector;
use PHPUnit\Framework\TestCase;

final class RedundantElseDetectorTest extends TestCase
{
    use ProvesAPythonRule;
    use FlagsEachSnippetOnce;

    private function rule(): Detector
    {
        return new RedundantElseDetector();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function thisSin(): iterable
    {
        yield 'return' => ["def a(x):\n    if x is None:\n        return 0\n    else:\n        return x.total\n"];
        yield 'raise' => ["def a(x):\n    if x is None:\n        raise Missing()\n    else:\n        use(x)\n"];
        yield 'continue' => ["def a(rows):\n    for row in rows:\n        if not row:\n            continue\n        else:\n            use(row)\n"];
        yield 'break' => ["def a(rows):\n    for row in rows:\n        if row.last:\n            break\n        else:\n            use(row)\n"];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notThisSin(): iterable
    {
        yield 'for/else means no break' => ["def a(rows):\n    for row in rows:\n        if row.hit:\n            return row\n    else:\n        return None\n"];
        yield 'while/else means no break' => ["def a(q):\n    while q:\n        if q.pop():\n            break\n    else:\n        report()\n"];
        yield 'an elif chain is a ladder, not a guard' => ["def a(x):\n    if x == 1:\n        return 'one'\n    elif x == 2:\n        return 'two'\n    else:\n        return 'many'\n"];
        yield 'a branch that falls through' => ["def a(x):\n    if x:\n        log(x)\n    else:\n        skip()\n"];
        yield 'an exit that is not the last statement' => ["def a(x):\n    if x:\n        if x.done:\n            return 1\n        log(x)\n    else:\n        skip()\n"];
        yield 'no else' => ["def a(x):\n    if x is None:\n        return 0\n    return x.total\n"];
    }
}
