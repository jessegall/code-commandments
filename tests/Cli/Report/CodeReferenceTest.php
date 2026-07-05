<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli\Report;

use JesseGall\CodeCommandments\Cli\Report\CodeReference;
use PHPUnit\Framework\TestCase;

final class CodeReferenceTest extends TestCase
{
    public function test_a_bare_path_has_no_lines(): void
    {
        $ref = CodeReference::parse('resources/js/Foo.vue');

        self::assertNotNull($ref);
        self::assertSame('resources/js/Foo.vue', $ref->path);
        self::assertNull($ref->startLine);
        self::assertNull($ref->endLine);
    }

    public function test_a_single_line(): void
    {
        $ref = CodeReference::parse('src/Foo.php:42');

        self::assertSame('src/Foo.php', $ref?->path);
        self::assertSame(42, $ref?->startLine);
        self::assertNull($ref?->endLine);
    }

    public function test_a_line_range(): void
    {
        $ref = CodeReference::parse('src/Foo.php:40-58');

        self::assertSame('src/Foo.php', $ref?->path);
        self::assertSame(40, $ref?->startLine);
        self::assertSame(58, $ref?->endLine);
    }

    public function test_a_non_numeric_tail_is_kept_as_part_of_the_path(): void
    {
        // A path that itself carries a colon (unusual, but must never be mis-split).
        $ref = CodeReference::parse('scheme:weird/Path.vue');

        self::assertSame('scheme:weird/Path.vue', $ref?->path);
        self::assertNull($ref?->startLine);
    }

    public function test_blank_is_null(): void
    {
        self::assertNull(CodeReference::parse('   '));
    }
}
