<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli;

use JesseGall\CodeCommandments\Cli\CodeSnippet;
use PHPUnit\Framework\TestCase;

/**
 * {@see CodeSnippet} embeds the flagged source in a `report` issue so a `file:line` into a
 * private/since-changed consumer tree is still reviewable.
 */
final class CodeSnippetTest extends TestCase
{
    private string $file;

    protected function setUp(): void
    {
        $this->file = sys_get_temp_dir() . '/cc-snippet-' . uniqid('', true) . '.php';
        $body = "<?php\n\nfinal class NodeGroup\n{\n    public const string GENERAL = 'General';\n}\n";
        file_put_contents($this->file, $body);
    }

    protected function tearDown(): void
    {
        @unlink($this->file);
    }

    public function test_renders_a_php_fenced_excerpt_marking_the_reported_line(): void
    {
        $snippet = new CodeSnippet()->forFile($this->file, 3);

        self::assertNotNull($snippet);
        self::assertStringContainsString('```php', $snippet);
        self::assertStringContainsString('→ 3  final class NodeGroup', $snippet);
        self::assertStringContainsString(":3`", $snippet);
    }

    public function test_windows_around_the_line_without_running_off_either_end(): void
    {
        $snippet = new CodeSnippet()->forFile($this->file, 5);

        self::assertNotNull($snippet);
        // Window is [line-3, line+24] clamped to the file: line 2 (first shown) .. line 6 (last).
        self::assertStringContainsString('  2  ', $snippet);
        self::assertStringContainsString("GENERAL = 'General'", $snippet);
        self::assertStringContainsString('  6  }', $snippet);
    }

    public function test_picks_the_fence_from_the_file_extension(): void
    {
        $vue = sys_get_temp_dir() . '/cc-snippet-' . uniqid('', true) . '.vue';
        file_put_contents($vue, "<template>\n  <div />\n</template>\n");

        $snippet = new CodeSnippet()->forFile($vue, 2);

        self::assertNotNull($snippet);
        self::assertStringContainsString('```vue', $snippet);

        @unlink($vue);
    }

    public function test_returns_null_when_the_file_is_absent(): void
    {
        self::assertNull(new CodeSnippet()->forFile('/no/such/file.php', 10));
    }
}
