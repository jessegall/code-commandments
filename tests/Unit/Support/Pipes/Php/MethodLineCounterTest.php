<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Support\Pipes\Php;

use JesseGall\CodeCommandments\Support\Pipes\Php\MethodLineCounter;
use JesseGall\CodeCommandments\Tests\TestCase;

class MethodLineCounterTest extends TestCase
{
    public function test_counts_all_lines_when_there_are_no_comments(): void
    {
        $content = <<<'PHP'
<?php
class Foo
{
    public function bar()
    {
        $a = 1;
        $b = 2;
        $c = 3;
    }
}
PHP;

        $this->assertSame(5, MethodLineCounter::count($content, 4, 8));
    }

    public function test_ignores_single_line_comments(): void
    {
        $content = <<<'PHP'
<?php
class Foo
{
    public function bar()
    {
        // leading comment
        $a = 1;
        // middle comment
        $b = 2;
        # shell-style comment
        $c = 3;
    }
}
PHP;

        // 8 total lines in method (4-11), 3 comment-only lines → 5
        $this->assertSame(5, MethodLineCounter::count($content, 4, 11));
    }

    public function test_ignores_block_comment_spanning_multiple_lines(): void
    {
        $content = <<<'PHP'
<?php
class Foo
{
    public function bar()
    {
        /*
         * This block spans
         * several lines
         */
        $a = 1;
    }
}
PHP;

        // method is lines 4-10 (7 lines), comment block covers lines 6-9 (4 lines) → 3
        $this->assertSame(3, MethodLineCounter::count($content, 4, 10));
    }

    public function test_ignores_docblock_comments(): void
    {
        $content = <<<'PHP'
<?php
class Foo
{
    public function bar()
    {
        /**
         * Docblock describing what follows.
         */
        $a = 1;
    }
}
PHP;

        // method is lines 4-9 (6 lines), docblock covers lines 6-8 (3 lines) → 3
        $this->assertSame(3, MethodLineCounter::count($content, 4, 9));
    }

    public function test_does_not_ignore_line_that_has_both_code_and_trailing_comment(): void
    {
        $content = <<<'PHP'
<?php
class Foo
{
    public function bar()
    {
        $a = 1; // trailing comment still has code
        $b = 2;
    }
}
PHP;

        $this->assertSame(4, MethodLineCounter::count($content, 4, 7));
    }

    public function test_does_not_ignore_blank_lines(): void
    {
        $content = <<<'PHP'
<?php
class Foo
{
    public function bar()
    {
        $a = 1;

        $b = 2;
    }
}
PHP;

        $this->assertSame(5, MethodLineCounter::count($content, 4, 8));
    }

    public function test_handles_block_comment_ending_on_line_with_code(): void
    {
        $content = <<<'PHP'
<?php
class Foo
{
    public function bar()
    {
        /* inline */ $a = 1;
        /* multi
           line */ $b = 2;
        $c = 3;
    }
}
PHP;

        // method lines 4-9 (6 lines). line 7 is comment-only (inside block), rest have code → 5
        $this->assertSame(5, MethodLineCounter::count($content, 4, 9));
    }
}
