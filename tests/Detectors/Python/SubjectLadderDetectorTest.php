<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Python;

use JesseGall\CodeCommandments\Detectors\Python\SubjectLadderDetector;
use JesseGall\CodeCommandments\Py\Codebase;
use JesseGall\CodeCommandments\Py\NodeMatch;
use PHPUnit\Framework\TestCase;

final class SubjectLadderDetectorTest extends TestCase
{
    public function test_flags_four_rungs_testing_one_subject(): void
    {
        $this->assertSame([2], $this->linesIn($this->ladder(['box', 'pallet', 'crate', 'bag'])));
    }

    public function test_the_subject_is_compared_as_parsed_so_spacing_and_side_do_not_matter(): void
    {
        $source = "def a(o):\n    if o.kind == 'box':\n        x()\n    elif 'pallet' == o . kind:\n        y()\n    elif o.kind == 'crate':\n        z()\n    elif (o.kind) == 'bag':\n        w()\n";

        $this->assertSame([2], $this->linesIn($source));
    }

    public function test_leaves_three_rungs_a_mixed_subject_and_other_tests(): void
    {
        $this->assertSame([], $this->linesIn($this->ladder(['box', 'pallet', 'crate'])));
        $this->assertSame([], $this->linesIn(str_replace("elif kind == 'bag'", "elif size == 'bag'", $this->ladder(['box', 'pallet', 'crate', 'bag']))));
        $this->assertSame([], $this->linesIn(str_replace("elif kind == 'bag'", "elif kind in ('bag', 'sack')", $this->ladder(['box', 'pallet', 'crate', 'bag']))));
        $this->assertSame([], $this->linesIn(str_replace("elif kind == 'bag'", "elif kind == other", $this->ladder(['box', 'pallet', 'crate', 'bag']))));
    }

    /**
     * @param  list<string>  $cases
     */
    private function ladder(array $cases): string
    {
        $source = "def a(kind):\n";

        foreach ($cases as $i => $case) {
            $source .= ($i === 0 ? '    if' : '    elif') . " kind == '{$case}':\n        handle_{$case}()\n";
        }

        return $source;
    }

    /**
     * @return list<int>
     */
    private function linesIn(string $source): array
    {
        return array_map(static fn (NodeMatch $match): int => $match->line(), new SubjectLadderDetector()->find(Codebase::fromString($source)));
    }
}
