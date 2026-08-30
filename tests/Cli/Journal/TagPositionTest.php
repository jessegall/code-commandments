<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli\Journal;

use JesseGall\CodeCommandments\Cli\Journal\Journal;
use JesseGall\CodeCommandments\Hooks\Handlers\JournalRecorder;
use JesseGall\CodeCommandments\Hooks\RecordingHookIO;
use JesseGall\CodeCommandments\Tests\Cli\FakeGit;
use JesseGall\CodeCommandments\Workspace;
use PHPUnit\Framework\TestCase;

/**
 * A tag is a declaration wherever it opens a line. A message arrives in flushes, and only the first was
 * ever read — so an agent that answered the user first and declared its work below had its declaration
 * silently dropped, and the write gate then refused the very edit it had just declared.
 */
final class TagPositionTest extends TestCase
{
    private string $root;

    private string|false $priorProjectDir;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/cc-tagpos-' . uniqid('', true);
        mkdir($this->root . '/.commandments', 0777, true);
        $this->priorProjectDir = getenv('CLAUDE_PROJECT_DIR');
        putenv('CLAUDE_PROJECT_DIR=' . $this->root);
    }

    protected function tearDown(): void
    {
        putenv($this->priorProjectDir === false ? 'CLAUDE_PROJECT_DIR' : 'CLAUDE_PROJECT_DIR=' . $this->priorProjectDir);
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    private function flush(string $delta, int $index): void
    {
        $io = new RecordingHookIO([
            'hook_event_name' => 'MessageDisplay',
            'session_id' => 'sess-tags',
            'turn_id' => 't',
            'message_id' => 'm',
            'index' => $index,
            'final' => true,
            'delta' => $delta,
        ], new FakeGit($this->root));

        new JournalRecorder($io)->run([]);
    }

    /**
     * @return list<string>
     */
    private function filed(): array
    {
        $filed = [];

        foreach (Journal::inSession(Workspace::at($this->root, 'sess-tags'))->entries() as $entry) {
            if ($entry->tag->isSome()) {
                $filed[] = $entry->text;
            }
        }

        return $filed;
    }

    public function test_a_tag_in_the_opening_flush_is_filed(): void
    {
        $this->flush('[!start] naming sessions', 0);

        $this->assertSame(['[!start] naming sessions'], $this->filed());
    }

    /**
     * The regression. Prose first, the declaration below it, and the tag arriving in a LATER flush —
     * which used to be discarded before anything looked for a tag in it.
     */
    public function test_a_tag_opening_a_line_in_a_later_flush_is_filed(): void
    {
        $this->flush("Good question, and the answer is yes.\n", 0);
        $this->flush("[!start] naming sessions\n", 1);

        $this->assertSame(['[!start] naming sessions'], $this->filed());
    }

    /**
     * A message may carry several. All of them are filed, not merely the first one seen.
     */
    public function test_every_tag_in_a_message_is_filed(): void
    {
        $this->flush("[!end] the old work\n[!start] the new work\n", 0);

        $this->assertSame(['[!end] the old work', '[!start] the new work'], $this->filed());
    }

    public function test_tags_spread_across_flushes_are_all_filed(): void
    {
        $this->flush("[!end] the old work\n", 0);
        $this->flush("Some prose in between.\n", 1);
        $this->flush("[!start] the new work\n", 2);

        $this->assertSame(['[!end] the old work', '[!start] the new work'], $this->filed());
    }

    /**
     * Narration from the middle of a message is not news. Reading every flush for TAGS must not turn the
     * index into a transcript — only the opening flush files untagged text.
     */
    public function test_untagged_narration_after_the_opening_flush_is_not_filed(): void
    {
        $this->flush("The opening line.\n", 0);
        $this->flush("More prose, no tag.\n", 1);

        $entries = Journal::inSession(Workspace::at($this->root, 'sess-tags'))->entries();

        $this->assertCount(1, $entries, 'only the opening line is filed');
        $this->assertSame('The opening line.', $entries[0]->text);
    }

    /**
     * A tag must OPEN its line. One mid-sentence is prose about a tag, not a declaration.
     */
    public function test_a_tag_mid_line_is_not_a_declaration(): void
    {
        $this->flush("I will write [!start] at the front next time.\n", 0);

        $this->assertSame([], $this->filed());
    }
}
