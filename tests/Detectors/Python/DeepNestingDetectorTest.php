<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Python;

use JesseGall\CodeCommandments\Detectors\Python\DeepNestingDetector;
use JesseGall\CodeCommandments\Py\Codebase;
use JesseGall\CodeCommandments\Py\NodeMatch;
use PHPUnit\Framework\TestCase;

final class DeepNestingDetectorTest extends TestCase
{
    public function test_flags_the_choice_that_opens_a_fourth_level_once_per_arrow(): void
    {
        $source = "def get(jar, name, domain):\n    for cookie in jar:\n        if cookie.name == name:\n            if cookie.domain == domain:\n                if cookie.fresh:\n                    if cookie.secure:\n                        return cookie\n";

        $this->assertSame([5], $this->linesIn($source));
    }

    public function test_loops_and_a_match_count_as_choices(): void
    {
        $source = "def pack(orders):\n    for order in orders:\n        for line in order.lines:\n            while line.left:\n                match line.kind:\n                    case 'box':\n                        box(line)\n";

        $this->assertSame([5], $this->linesIn($source));
    }

    public function test_three_choices_deep_is_at_the_limit(): void
    {
        $this->assertSame([], $this->linesIn("def a(rows):\n    for row in rows:\n        if row:\n            if row.ok:\n                use(row)\n"));
    }

    public function test_an_elif_try_and_with_add_no_level(): void
    {
        $source = "def a(rows):\n    for row in rows:\n        try:\n            with row.lock():\n                if row.a:\n                    pass\n                elif row.b:\n                    if row.c:\n                        use(row)\n        except Busy:\n            pass\n";

        $this->assertSame([], $this->linesIn($source));
    }

    public function test_a_nested_def_counts_from_zero(): void
    {
        $source = "def a(rows):\n    for row in rows:\n        if row:\n            def inner(x):\n                if x:\n                    if x.y:\n                        use(x)\n            inner(row)\n";

        $this->assertSame([], $this->linesIn($source));
    }

    /**
     * @return list<int>
     */
    private function linesIn(string $source): array
    {
        return array_map(static fn (NodeMatch $match): int => $match->line(), new DeepNestingDetector()->find(Codebase::fromString($source)));
    }
}
