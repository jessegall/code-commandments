<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Architecture;

use PHPUnit\Framework\TestCase;

/**
 * A hook that runs between the agent and its next keystroke must cost nothing, and the session transcript
 * is the one file in reach that is genuinely large — tens of megabytes for a long session. No hook opens
 * one. The transcript is read where it is ASKED for, by a command a person ran.
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
            'It is the largest file in reach and a hook must cost nothing: tens of megabytes read',
            'between the agent and its next keystroke.',
        ]));
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
