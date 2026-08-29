<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli\Journal;

use JesseGall\CodeCommandments\Cli\Journal\Category;
use JesseGall\CodeCommandments\Cli\Journal\Digest;
use JesseGall\CodeCommandments\Cli\Journal\Line;
use PHPUnit\Framework\TestCase;

/**
 * Choosing what is worth reading back. The rules are what a compaction summary gets wrong: the user's own
 * words survive whole, enough of the agent's sit either side of them to say what was being answered, and
 * the long stretches it worked alone are kept only where it said what it carried.
 */
final class DigestTest extends TestCase
{
    private function user(string $text): Line
    {
        return new Line(Category::Prompt, '2026-08-29T10:00:00Z', $text);
    }

    private function agent(string $text): Line
    {
        return new Line(Category::Reply, '2026-08-29T10:00:00Z', $text);
    }

    /**
     * @param  list<Line>  $lines
     * @return list<string>
     */
    private function chosen(array $lines): array
    {
        return array_map(fn (Line $line) => $line->text, new Digest($lines)->selected());
    }

    /**
     * A bare "yes please" means nothing without the thing it answered, so the messages around a prompt come
     * with it even when they said nothing about themselves.
     */
    public function test_a_prompt_brings_its_context_with_it(): void
    {
        $chosen = $this->chosen([
            $this->agent('untagged, long ago'),
            $this->agent('untagged, further back'),
            $this->agent('what shall I do about the drilldown?'),
            $this->user('yes please'),
            $this->agent('doing it now'),
            $this->agent('untagged, well after'),
        ]);

        $this->assertContains('what shall I do about the drilldown?', $chosen);
        $this->assertContains('yes please', $chosen);
        $this->assertContains('doing it now', $chosen);
        $this->assertNotContains('untagged, long ago', $chosen);
        $this->assertNotContains('untagged, well after', $chosen);
    }

    /**
     * Through a stretch the agent worked alone, only the messages that said what they carried are kept.
     */
    public function test_an_autonomous_stretch_keeps_only_what_was_tagged(): void
    {
        $lines = [$this->user('go')];

        for ($i = 0; $i < 20; $i++) {
            $lines[] = $this->agent("routine step {$i}");
        }

        $lines[] = $this->agent('[discovery] the pattern already exists');
        $lines[] = $this->agent('[correction] I had that backwards');

        $chosen = $this->chosen($lines);

        $this->assertContains('[discovery] the pattern already exists', $chosen);
        $this->assertContains('[correction] I had that backwards', $chosen);
        $this->assertNotContains('routine step 12', $chosen);
    }

    /**
     * The harness records a message as it is sent and again when more of it follows, so the same words
     * arrive twice with the second carrying the rest.
     */
    public function test_a_prompt_the_user_kept_typing_counts_once(): void
    {
        $chosen = $this->chosen([
            $this->user('motion.ts is FORBIDDEN'),
            $this->user("motion.ts is FORBIDDEN\n\nWE HAVE NO ANIMATIONS IN THE FRONTEND"),
        ]);

        $this->assertSame(["motion.ts is FORBIDDEN\n\nWE HAVE NO ANIMATIONS IN THE FRONTEND"], $chosen);
    }

    /**
     * Two prompts that merely start alike are two things the user said, not one they finished.
     */
    public function test_two_different_prompts_are_both_kept(): void
    {
        $this->assertCount(2, $this->chosen([$this->user('fix the tests'), $this->user('fix the docs')]));
    }

    /**
     * The user's words are the tier that is never cut — a long ruling is the thing most worth having.
     */
    public function test_the_users_words_are_never_truncated(): void
    {
        $ruling = str_repeat('this is a long standing ruling. ', 60);

        $this->assertStringContainsString(trim($ruling), new Digest([$this->user($ruling)])->render());
    }

    public function test_pinned_facts_stand_at_the_top(): void
    {
        $rendered = new Digest([
            $this->agent('[start] the reader'),
            $this->user('carry on'),
            $this->agent('[pinned] motion.ts is FORBIDDEN'),
        ])->render();

        $this->assertStringContainsString('── pinned', $rendered);
        $this->assertLessThan(
            mb_strpos($rendered, 'carry on'),
            mb_strpos($rendered, 'motion.ts is FORBIDDEN'),
        );
    }

    /**
     * A stretch that was dropped is SAID to have been dropped — a silent gap reads as a conversation that
     * never happened.
     */
    public function test_a_skipped_stretch_is_marked_rather_than_silently_dropped(): void
    {
        $lines = [$this->user('go')];

        for ($i = 0; $i < 12; $i++) {
            $lines[] = $this->agent("routine step {$i}");
        }

        $lines[] = $this->agent('[end] done');

        $this->assertStringContainsString('messages ⋯', new Digest($lines)->render());
    }

    public function test_nothing_said_renders_as_nothing(): void
    {
        $this->assertSame('', new Digest([])->render());
    }
}
