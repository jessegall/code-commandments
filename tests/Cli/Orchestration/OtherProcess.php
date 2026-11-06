<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli\Orchestration;

use RuntimeException;

/**
 * A second process asked the same question at the same time. It speaks through two files rather than a
 * pipe, because what a test needs of it is not its output but its TIMING: whether it has got as far as
 * asking ({@see reached}) and whether it has been answered yet ({@see answer}).
 *
 * The two are separate marks on purpose. "It has not answered" proves nothing on its own — a process
 * that has not started yet has not answered either — so a blocked process must first say it is here.
 */
final readonly class OtherProcess
{
    /**
     * How long a second process is given to boot before the test gives up on it.
     */
    private const int PATIENCE_MICROSECONDS = 5_000_000;

    private const int POLL_MICROSECONDS = 5_000;

    public function __construct(
        public string $reached,
        public string $answer,
    ) {}

    /**
     * Wait until it has reached the question. Throws rather than returning, since every assertion after
     * this one would otherwise pass for the wrong reason.
     */
    public function waitToReach(): void
    {
        $this->waitFor($this->reached, 'never started');
    }

    public function hasAnswered(): bool
    {
        return is_file($this->answer);
    }

    /**
     * What it answered, waiting for it if it is still going.
     */
    public function answer(): string
    {
        $this->waitFor($this->answer, 'never answered');

        return trim((string) file_get_contents($this->answer));
    }

    private function waitFor(string $file, string $complaint): void
    {
        for ($waited = 0; $waited < self::PATIENCE_MICROSECONDS; $waited += self::POLL_MICROSECONDS) {
            if (is_file($file)) {
                return;
            }

            usleep(self::POLL_MICROSECONDS);
        }

        throw new RuntimeException("The second process {$complaint}.");
    }
}
