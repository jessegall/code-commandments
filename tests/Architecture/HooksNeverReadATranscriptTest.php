<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Architecture;

use JesseGall\CodeCommandments\Cli\Journal\Transcript;
use PHPUnit\Framework\TestCase;

/**
 * A hook runs between the agent and its next keystroke, so it must cost nothing. The session transcript is
 * the one file in reach that is genuinely large — a long session's runs to tens of megabytes — and reading
 * it is a deliberate act a HUMAN asks for (`commandments journal`), never something a hook does on the way
 * past. The journal exists precisely so a hook never has to: it is an index, and reading it is sub-millisecond.
 */
final class HooksNeverReadATranscriptTest extends TestCase
{
    public function test_no_hook_reads_a_transcript(): void
    {
        $offenders = [];

        foreach ($this->hookSources() as $path) {
            if (str_contains((string) file_get_contents($path), 'Transcript')) {
                $offenders[] = basename($path);
            }
        }

        $this->assertSame([], $offenders, implode("\n", [
            'A hook reached for the transcript: ' . implode(', ', $offenders) . '.',
            'It is the largest file in reach and a hook must cost nothing — ask the Journal instead,',
            'which is the index that exists so a hook never has to open the record itself.',
        ]));
    }

    /**
     * Reading the whole record is allowed where it is ASKED for, and stays cheap because it streams — the
     * lines are yielded, never gathered into one array first.
     */
    public function test_the_deliberate_full_read_streams_rather_than_slurping(): void
    {
        $lines = new Transcript(__DIR__ . '/../Fixtures/journal/session.jsonl')->lines();

        $this->assertInstanceOf(\Generator::class, $lines);
    }

    /**
     * @return list<string>
     */
    private function hookSources(): array
    {
        $found = [];

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(__DIR__ . '/../../src/Hooks')) as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $found[] = $file->getPathname();
            }
        }

        return $found;
    }
}
