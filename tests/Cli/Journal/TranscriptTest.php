<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli\Journal;

use JesseGall\CodeCommandments\Cli\Journal\Category;
use JesseGall\CodeCommandments\Cli\Journal\Line;
use JesseGall\CodeCommandments\Cli\Journal\Tag;
use JesseGall\CodeCommandments\Cli\Journal\Transcript;
use PHPUnit\Framework\TestCase;

/**
 * Reading a session transcript. The harness writes a great deal under `type: "user"` that nobody typed, so
 * every assertion here is about telling those apart by their FIELDS — the fixture holds one of each.
 */
final class TranscriptTest extends TestCase
{
    private function transcript(): Transcript
    {
        return new Transcript(__DIR__ . '/../../Fixtures/journal/session.jsonl');
    }

    /**
     * @return list<Line>
     */
    private function lines(): array
    {
        return iterator_to_array($this->transcript()->lines(), false);
    }

    /**
     * @return list<Line>
     */
    private function of(Category $category): array
    {
        return array_values(array_filter($this->lines(), fn (Line $line) => $line->category === $category));
    }

    /**
     * The point of the whole reader: four different things are written under `type: "user"` and only one
     * of them is a person.
     */
    public function test_only_a_human_prompt_counts_as_the_user_speaking(): void
    {
        $prompts = $this->of(Category::Prompt);

        $this->assertSame(
            ['we should not send the default values over the wire', 'motion.ts is FORBIDDEN'],
            array_map(fn (Line $line) => $line->text, $prompts),
        );
    }

    public function test_a_tool_result_is_not_the_user(): void
    {
        $this->assertCount(1, $this->of(Category::ToolResult));
    }

    /**
     * A synthesized turn, a hook attachment and a stop-hook summary are all addressed TO the agent — none
     * of them is anybody speaking.
     */
    public function test_injected_context_is_not_the_user(): void
    {
        $injected = array_map(fn (Line $line) => $line->text, $this->of(Category::Injected));

        $this->assertContains('Continue from where you left off.', $injected);
        $this->assertContains('Code Commandments — cardinal rule', $injected);
        $this->assertContains('stop hook fired', $injected);
    }

    public function test_the_agents_prose_is_read_without_its_tool_calls(): void
    {
        $replies = array_map(fn (Line $line) => $line->text, $this->of(Category::Reply));

        $this->assertSame([
            '[r] I had it backwards — defaults DO travel to the client.',
            '[s] removing the Owned attribute',
            '[e] removing the Owned attribute',
        ], $replies);
    }

    /**
     * A compaction writes its own boundary into the transcript, so the chunks are recoverable from the
     * record itself — no hook had to be present when it happened.
     */
    public function test_a_compaction_boundary_is_read_from_the_record(): void
    {
        $this->assertSame(1, $this->transcript()->compactions());
        $this->assertCount(1, $this->of(Category::Boundary));
        $this->assertSame('The conversation was about a Vue migration.', $this->of(Category::Summary)[0]->text);
    }

    public function test_chunks_are_counted_back_from_the_present(): void
    {
        $current = array_filter($this->transcript()->chunk(0), fn (Line $line) => $line->isPrompt());
        $before = array_filter($this->transcript()->chunk(1), fn (Line $line) => $line->isPrompt());

        $this->assertSame(['motion.ts is FORBIDDEN'], array_values(array_map(fn (Line $line) => $line->text, $current)));
        $this->assertSame(['we should not send the default values over the wire'], array_values(array_map(fn (Line $line) => $line->text, $before)));
    }

    public function test_bookkeeping_rows_carry_nothing_and_say_nothing(): void
    {
        foreach ($this->of(Category::Bookkeeping) as $line) {
            $this->assertSame('', $line->text);
            $this->assertFalse($line->isSpeech());
        }
    }

    public function test_a_line_knows_the_tag_it_wore(): void
    {
        $replies = $this->of(Category::Reply);

        $this->assertSame(Tag::Reply, $replies[0]->tag()->unwrap());
        $this->assertSame(Tag::Start, $replies[1]->tag()->unwrap());
        $this->assertSame(Tag::End, $replies[2]->tag()->unwrap());
    }

    public function test_a_missing_transcript_reads_as_empty_rather_than_failing(): void
    {
        $missing = new Transcript('/nowhere/at/all.jsonl');

        $this->assertFalse($missing->exists());
        $this->assertSame([], iterator_to_array($missing->lines(), false));
    }
}
