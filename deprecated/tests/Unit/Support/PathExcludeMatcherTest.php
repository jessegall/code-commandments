<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Support;

use JesseGall\CodeCommandments\Support\PathExcludeMatcher;
use JesseGall\CodeCommandments\Tests\TestCase;

class PathExcludeMatcherTest extends TestCase
{
    public function test_literal_substring_match(): void
    {
        $this->assertTrue(
            PathExcludeMatcher::matchesAny('/app/Console/Kernel.php', ['Console/Kernel.php'])
        );
    }

    public function test_wildcard_extension_match(): void
    {
        $this->assertTrue(
            PathExcludeMatcher::matchesAny('/app/types.d.ts', ['*.d.ts'])
        );
    }

    public function test_wildcard_min_js_match(): void
    {
        $this->assertTrue(
            PathExcludeMatcher::matchesAny('/resources/js/app.min.js', ['*.min.js'])
        );
    }

    public function test_wildcard_does_not_match_unrelated_file(): void
    {
        $this->assertFalse(
            PathExcludeMatcher::matchesAny('/app/Models/User.php', ['*.d.ts'])
        );
    }

    public function test_empty_patterns_never_match(): void
    {
        $this->assertFalse(PathExcludeMatcher::matchesAny('/any/path.php', []));
    }

    public function test_regex_metacharacters_are_escaped(): void
    {
        // `.` in the pattern should match a literal `.`, not any char, except where the * expansion applies
        $this->assertFalse(
            PathExcludeMatcher::matchesAny('/app/foo_php', ['foo.php'])
        );
        $this->assertTrue(
            PathExcludeMatcher::matchesAny('/app/foo.php', ['foo.php'])
        );
    }

    public function test_multiple_patterns_any_match_wins(): void
    {
        $this->assertTrue(
            PathExcludeMatcher::matchesAny(
                '/app/types.d.ts',
                ['Console/Kernel.php', '*.d.ts', 'dist']
            )
        );
    }

    public function test_trailing_slash_is_stripped(): void
    {
        $this->assertTrue(
            PathExcludeMatcher::matchesAny('/app/node_modules/foo.js', ['node_modules/'])
        );
    }

    public function test_to_regex_produces_delimited_pattern(): void
    {
        $this->assertSame('/node_modules/', PathExcludeMatcher::toRegex('node_modules'));
        $this->assertSame('/.*\.d\.ts/', PathExcludeMatcher::toRegex('*.d.ts'));
    }

    // ────────────────────────────────────────────────────────────────
    // shouldExclude — unified entry point used by every scan path
    // ────────────────────────────────────────────────────────────────

    public function test_should_exclude_applies_default_excludes(): void
    {
        $this->assertTrue(PathExcludeMatcher::shouldExclude('/abs/proj/vendor/foo/Bar.php', []));
        $this->assertTrue(PathExcludeMatcher::shouldExclude('/abs/proj/node_modules/x/index.js', []));
        $this->assertTrue(PathExcludeMatcher::shouldExclude('/abs/proj/storage/cache/x', []));
        $this->assertTrue(PathExcludeMatcher::shouldExclude('/abs/proj/.git/HEAD', []));
        $this->assertTrue(PathExcludeMatcher::shouldExclude('/abs/proj/bootstrap/cache/x.php', []));
    }

    public function test_should_exclude_can_skip_default_excludes(): void
    {
        $this->assertFalse(
            PathExcludeMatcher::shouldExclude('/abs/proj/vendor/foo/Bar.php', [], applyDefaults: false)
        );
    }

    public function test_should_exclude_matches_user_absolute_paths(): void
    {
        $this->assertTrue(
            PathExcludeMatcher::shouldExclude(
                '/abs/proj/tests/Feature/Octane/WorkerTest.php',
                ['/abs/proj/tests/'],
            )
        );
    }

    public function test_should_exclude_matches_user_relative_paths(): void
    {
        $this->assertTrue(
            PathExcludeMatcher::shouldExclude(
                '/abs/proj/tests/Feature/Octane/WorkerTest.php',
                ['tests'],
            )
        );
    }

    public function test_should_exclude_returns_false_for_non_matching_paths(): void
    {
        $this->assertFalse(
            PathExcludeMatcher::shouldExclude(
                '/abs/proj/src/Service.php',
                ['tests', 'database'],
            )
        );
    }
}
