<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Python;

use JesseGall\CodeCommandments\Detectors\Python\RedundantElseDetector;
use JesseGall\CodeCommandments\Py\Codebase;
use JesseGall\CodeCommandments\Py\NodeMatch;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RedundantElseDetectorTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function exits(): iterable
    {
        yield 'return' => ["def a(x):\n    if x is None:\n        return 0\n    else:\n        return x.total\n"];
        yield 'raise' => ["def a(x):\n    if x is None:\n        raise Missing()\n    else:\n        use(x)\n"];
        yield 'continue' => ["def a(rows):\n    for row in rows:\n        if not row:\n            continue\n        else:\n            use(row)\n"];
        yield 'break' => ["def a(rows):\n    for row in rows:\n        if row.last:\n            break\n        else:\n            use(row)\n"];
    }

    #[DataProvider('exits')]
    public function test_flags_an_else_after_a_branch_that_left(string $source): void
    {
        $this->assertCount(1, $this->findIn($source));
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

    #[DataProvider('notThisSin')]
    public function test_leaves_what_is_not_an_else_after_an_exit(string $source): void
    {
        $this->assertSame([], $this->findIn($source));
    }

    /**
     * @return list<NodeMatch>
     */
    private function findIn(string $source): array
    {
        return new RedundantElseDetector()->find(Codebase::fromString($source));
    }
}
