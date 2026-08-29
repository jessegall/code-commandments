<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Architecture;

use JesseGall\CodeCommandments\Cli\Journal\Transcript;
use PHPUnit\Framework\TestCase;

/**
 * A hook that runs between the agent and its next keystroke must cost nothing, and the session transcript
 * is the one file in reach that is genuinely large — tens of megabytes for a long session. So a hook bound
 * to a PER-TOOL-CALL moment never opens one; the journal is the index that exists so it never has to.
 *
 * A hook bound only to the END of a turn is a different case: it fires once, where a read costs about a
 * tenth of a second, and it is the one moment an agent can still act on what it learns. {@see EXEMPT} names
 * those, with the reason, so the exemption is a decision rather than a gap.
 */
final class HooksNeverReadATranscriptTest extends TestCase
{
    /**
     * Hooks that may open a transcript, and why. Each fires ONCE at the end of a turn, never per tool call.
     */
    private const array EXEMPT = [
        // Tells the agent whether the record heard what it said — which needs both records, and is only
        // worth knowing at the moment it believes it has finished.
        'JournalReminder.php',
    ];

    public function test_no_hook_reads_a_transcript(): void
    {
        $offenders = [];

        foreach ($this->hookSources() as $path) {
            if (in_array(basename($path), self::EXEMPT, true)) {
                continue;
            }

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
    /**
     * An exempt hook must bind ONLY to the end of a turn. The moment one takes a per-tool-call binding, the
     * exemption is no longer about a once-a-turn read and the cost returns.
     */
    public function test_an_exempt_hook_fires_only_at_the_end_of_a_turn(): void
    {
        foreach (self::EXEMPT as $file) {
            $source = (string) file_get_contents(__DIR__ . '/../../src/Hooks/Handlers/' . $file);

            $this->assertStringNotContainsString("HookBinding('PreToolUse'", $source, "{$file} may not read a transcript per tool call");
        }
    }

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
