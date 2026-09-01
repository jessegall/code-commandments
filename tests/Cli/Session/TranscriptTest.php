<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli\Session;

use JesseGall\CodeCommandments\Cli\Session\Transcript;
use PHPUnit\Framework\TestCase;

/**
 * What a session is CALLED, read off the record the harness writes. The name is the whole of what this
 * package asks a transcript for: `session list` prints folders named after a hash, and a hash says
 * nothing about which conversation left it there.
 */
final class TranscriptTest extends TestCase
{
    /**
     * @var list<string>
     */
    private array $written = [];

    protected function tearDown(): void
    {
        foreach ($this->written as $path) {
            @unlink($path);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function transcript(array $rows): Transcript
    {
        $path = sys_get_temp_dir() . '/cc-transcript-' . uniqid('', true) . '.jsonl';

        file_put_contents($path, implode("\n", array_map(json_encode(...), $rows)) . "\n");

        $this->written[] = $path;

        return new Transcript($path);
    }

    /**
     * The harness's own title wins where it has written one — it is what the user sees the session called
     * everywhere else, and two names for one conversation is one too many.
     */
    public function test_a_session_is_named_by_the_title_the_harness_generated(): void
    {
        $said = $this->transcript([
            ['type' => 'user', 'promptSource' => 'typed', 'message' => ['content' => 'fix the drilldown']],
            ['type' => 'ai-title', 'aiTitle' => 'The Vue migration'],
        ]);

        $this->assertSame('The Vue migration', $said->name());
    }

    /**
     * With no title, the first thing a HUMAN said. Only a typed prompt counts: a transcript's `user` lines
     * are mostly tool results and injected context, and naming a session after one of those names it
     * after the machinery rather than the work.
     */
    public function test_with_no_title_it_is_named_by_the_first_thing_the_user_said(): void
    {
        $said = $this->transcript([
            ['type' => 'user', 'toolUseResult' => ['ok' => true], 'message' => ['content' => 'a tool result']],
            ['type' => 'user', 'promptSource' => 'typed', 'message' => ['content' => 'fix the drilldown']],
        ]);

        $this->assertSame('fix the drilldown', $said->name());
    }

    public function test_a_missing_transcript_reads_as_empty_rather_than_failing(): void
    {
        $missing = new Transcript('/nowhere/at/all.jsonl');

        $this->assertFalse($missing->exists());
        $this->assertSame('', $missing->name());
    }
}
