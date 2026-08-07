<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Agents;

use JesseGall\CodeCommandments\Agents\Instructions;
use JesseGall\CodeCommandments\Support\GeneratedBlock;
use PHPUnit\Framework\TestCase;

/**
 * The one rule this class exists to keep: a project's instructions file may GAIN our block and must
 * never LOSE a byte of its own. It is a file a human wrote, edited under a `composer update` nobody
 * is watching, and there is no undo — so every shape a real file comes in gets its own case here,
 * and each asserts the surrounding text survives EXACTLY, not merely that it is still mentioned.
 */
final class InstructionsTest extends TestCase
{
    private const string NAME = 'code-commandments briefing';

    private string $dir;

    protected function setUp(): void
    {
        $this->dir = (string) realpath(sys_get_temp_dir()) . '/cc-instr-' . uniqid('', true);
        mkdir($this->dir, 0775, true);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->dir));
    }

    public function test_it_creates_the_file_when_there_is_none(): void
    {
        $this->assertTrue($this->instructions('AGENTS.md')->inject(self::NAME, 'the briefing'));

        $this->assertStringContainsString('the briefing', $this->read('AGENTS.md'));
        $this->assertStringContainsString(GeneratedBlock::end(self::NAME), $this->read('AGENTS.md'));
    }

    public function test_it_appends_to_a_file_that_has_no_block_and_keeps_every_line(): void
    {
        $mine = "# My project\n\nRun the tests with `make test`.\n\n## Style\n\nTabs, sadly.\n";
        $this->write('AGENTS.md', $mine);

        $this->instructions('AGENTS.md')->inject(self::NAME, 'the briefing');

        $this->assertSame($mine, $this->beforeTheBlock('AGENTS.md'), 'every original line, byte for byte');
    }

    public function test_it_appends_rather_than_landing_inside_front_matter(): void
    {
        // A file shared with a tool that requires front-matter. Inserting "after the first line"
        // puts our block between the opening `---` and the keys, which invalidates the whole file.
        $mine = "---\ndescription: my rules\nalwaysApply: true\n---\n\n# Rules\n\nBe kind.\n";
        $this->write('AGENTS.md', $mine);

        $this->instructions('AGENTS.md')->inject(self::NAME, 'the briefing');

        $this->assertSame($mine, $this->beforeTheBlock('AGENTS.md'));
        $this->assertStringStartsWith("---\ndescription: my rules\n", $this->read('AGENTS.md'));
    }

    public function test_it_appends_rather_than_landing_inside_an_opening_code_fence(): void
    {
        $mine = "```sh\nmake test\nmake lint\n```\n\nThat is how you run it.\n";
        $this->write('AGENTS.md', $mine);

        $this->instructions('AGENTS.md')->inject(self::NAME, 'the briefing');

        // Inside the fence the block would be inert text — the briefing silently absent, and the
        // user's code sample polluted.
        $this->assertSame($mine, $this->beforeTheBlock('AGENTS.md'));
    }

    public function test_it_replaces_the_block_and_leaves_the_prose_around_it(): void
    {
        $this->write('AGENTS.md', "# Mine\n\nabove\n\n" . $this->block('old briefing') . "\n\nbelow\n");

        $this->instructions('AGENTS.md')->inject(self::NAME, 'new briefing');

        $updated = $this->read('AGENTS.md');

        $this->assertStringContainsString('new briefing', $updated);
        $this->assertStringNotContainsString('old briefing', $updated);
        $this->assertStringContainsString("# Mine\n\nabove\n", $updated);
        $this->assertStringContainsString("\nbelow\n", $updated);
    }

    public function test_a_second_injection_of_the_same_body_changes_nothing(): void
    {
        $this->write('AGENTS.md', "# Mine\n\nprose\n");
        $this->instructions('AGENTS.md')->inject(self::NAME, 'the briefing');
        $once = $this->read('AGENTS.md');

        $this->instructions('AGENTS.md')->inject(self::NAME, 'the briefing');

        $this->assertSame($once, $this->read('AGENTS.md'), 'a sync that changes nothing must write nothing');
    }

    public function test_a_marker_the_user_quoted_in_prose_is_not_treated_as_the_block(): void
    {
        $mine = "# Mine\n\nThe tool writes a `" . GeneratedBlock::begin(self::NAME, 'composer update') . "` marker.\n\nMy notes below.\n";
        $this->write('AGENTS.md', $mine);

        $this->instructions('AGENTS.md')->inject(self::NAME, 'the briefing');

        $this->assertSame($mine, $this->beforeTheBlock('AGENTS.md'), 'the sentence about the marker is still a sentence');
    }

    public function test_a_begin_with_no_end_is_refused_and_nothing_is_written(): void
    {
        $mine = "# Mine\n" . GeneratedBlock::begin(self::NAME, 'composer update') . "\nwords the user owns now\n";
        $this->write('AGENTS.md', $mine);

        $this->assertFalse($this->instructions('AGENTS.md')->inject(self::NAME, 'the briefing'));
        $this->assertSame($mine, $this->read('AGENTS.md'));
    }

    public function test_two_blocks_are_refused_and_nothing_is_written(): void
    {
        $mine = $this->block('one') . "\n\nthe user's words between\n\n" . $this->block('two') . "\n";
        $this->write('AGENTS.md', $mine);

        $this->assertFalse($this->instructions('AGENTS.md')->inject(self::NAME, 'the briefing'));
        $this->assertSame($mine, $this->read('AGENTS.md'));
    }

    public function test_it_keeps_crlf_line_endings(): void
    {
        $this->write('AGENTS.md', "# Mine\r\n\r\nprose\r\n");

        $this->instructions('AGENTS.md')->inject(self::NAME, 'the briefing');

        // A block written with bare newlines gives the file mixed endings, and under `core.autocrlf`
        // that churns the whole file on every sync.
        $this->assertStringNotContainsString("\n\n", str_replace("\r\n", '', $this->read('AGENTS.md')));
        $this->assertStringContainsString("the briefing\r\n", $this->read('AGENTS.md'));
    }

    public function test_it_keeps_a_byte_order_mark_at_the_front(): void
    {
        $this->write('AGENTS.md', "\xEF\xBB\xBF# Mine\n\nprose\n");

        $this->instructions('AGENTS.md')->inject(self::NAME, 'the briefing');

        $this->assertStringStartsWith("\xEF\xBB\xBF# Mine", $this->read('AGENTS.md'));
        $this->assertSame(1, substr_count($this->read('AGENTS.md'), "\xEF\xBB\xBF"), 'and only at the front');
    }

    public function test_a_file_resolving_outside_the_project_is_left_alone(): void
    {
        $shared = "{$this->dir}/dotfiles/AGENTS.md";
        @mkdir(dirname($shared), 0775, true);
        file_put_contents($shared, "shared across every project\n");

        @mkdir("{$this->dir}/project", 0775, true);
        symlink($shared, "{$this->dir}/project/AGENTS.md");

        $outside = new Instructions("{$this->dir}/project/AGENTS.md", "{$this->dir}/project");

        $this->assertFalse($outside->inject(self::NAME, 'the briefing'));
        $this->assertSame("shared across every project\n", (string) file_get_contents($shared));
    }

    public function test_two_names_for_one_file_are_recognised_as_one(): void
    {
        $this->write('AGENTS.md', "# Mine\n");
        link("{$this->dir}/AGENTS.md", "{$this->dir}/CLAUDE.md");

        // A hard link gives two different realpaths and one file: comparing paths says "two", and
        // the second write then replaces the first block instead of sitting beside it.
        $this->assertTrue($this->instructions('CLAUDE.md')->isSameFileAs($this->instructions('AGENTS.md')));
    }

    public function test_two_files_that_merely_do_not_exist_yet_are_not_the_same_file(): void
    {
        // `realpath` returns false for both, and `false === false` would call a fresh project's two
        // absent files one — so the pointer would never be written at all.
        $this->assertFalse($this->instructions('CLAUDE.md')->isSameFileAs($this->instructions('AGENTS.md')));
    }

    private function instructions(string $name): Instructions
    {
        return new Instructions("{$this->dir}/{$name}", $this->dir);
    }

    private function block(string $body): string
    {
        return GeneratedBlock::begin(self::NAME, 'composer update') . "\n{$body}\n" . GeneratedBlock::end(self::NAME);
    }

    private function write(string $name, string $contents): void
    {
        file_put_contents("{$this->dir}/{$name}", $contents);
    }

    private function read(string $name): string
    {
        return (string) file_get_contents("{$this->dir}/{$name}");
    }

    /**
     * Everything ahead of the block we appended — which must be the user's file, unchanged.
     */
    private function beforeTheBlock(string $name): string
    {
        $marker = GeneratedBlock::begin(self::NAME, 'composer update');
        $kept = [];

        // Line-anchored, like the subject: a marker QUOTED in a sentence is prose, and a helper
        // that cut at the first substring would report the user's own words as deleted.
        foreach (explode("\n", $this->read($name)) as $line) {
            if (trim($line) === $marker) {
                break;
            }

            $kept[] = $line;
        }

        return rtrim(implode("\n", $kept), "\n") . "\n";
    }
}
