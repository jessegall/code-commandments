<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Python;

use JesseGall\CodeCommandments\Detectors\Python\LoopWrappedInIfDetector;
use JesseGall\CodeCommandments\Py\Codebase;
use JesseGall\CodeCommandments\Py\NodeMatch;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LoopWrappedInIfDetectorTest extends TestCase
{
    public function test_flags_a_for_or_while_body_that_is_one_if_around_work(): void
    {
        $this->assertSame([3], $this->linesIn("def a(rows):\n    for row in rows:\n        if row.ok:\n            row.save()\n            count(row)\n"));
        $this->assertSame([3], $this->linesIn("def a(q):\n    while q:\n        if q.ready():\n            item = q.pop()\n            handle(item)\n"));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notThisSin(): iterable
    {
        yield 'a one-line filter' => ["def a(rows):\n    for row in rows:\n        if row.ok:\n            keep(row)\n"];
        yield 'a search that leaves' => ["def a(rows):\n    for row in rows:\n        if row.ok:\n            row.touch()\n            return row\n"];
        yield 'an if with an else' => ["def a(rows):\n    for row in rows:\n        if row.ok:\n            row.save()\n            count(row)\n        else:\n            skip(row)\n"];
        yield 'more than the if in the body' => ["def a(rows):\n    for row in rows:\n        log(row)\n        if row.ok:\n            row.save()\n            count(row)\n"];
        yield 'the loop else, not its body' => ["def a(rows):\n    for row in rows:\n        use(row)\n    else:\n        if rows:\n            done(rows)\n            log(rows)\n"];
    }

    #[DataProvider('notThisSin')]
    public function test_leaves_what_is_not_a_wrapped_body(string $source): void
    {
        $this->assertSame([], $this->linesIn($source));
    }

    /**
     * @return list<int>
     */
    private function linesIn(string $source): array
    {
        return array_map(static fn (NodeMatch $match): int => $match->line(), new LoopWrappedInIfDetector()->find(Codebase::fromString($source)));
    }
}
