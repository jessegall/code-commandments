<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli;

use JesseGall\CodeCommandments\Cli\Scope\ChangedLines;
use PHPUnit\Framework\TestCase;

final class ChangedLinesTest extends TestCase
{
    public function test_a_diff_covers_the_lines_it_adds_and_the_line_they_sit_above(): void
    {
        $lines = ChangedLines::fromDiff("diff --git a/x b/x\n@@ -3,0 +4,2 @@\n+a\n+b\n@@ -20 +22 @@\n-c\n+d\n@@ -30,2 +31,0 @@\n-e\n-f\n");

        $this->assertSame(
            [4, 5, 6, 22, 23, 31],
            array_values(array_filter(range(1, 40), $lines->covers(...))),
        );
    }

    public function test_a_file_git_does_not_track_is_changed_everywhere(): void
    {
        $this->assertTrue(ChangedLines::everywhere()->covers(999));
    }
}
