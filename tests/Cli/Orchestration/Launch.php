<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli\Orchestration;

/**
 * One detached command a trigger asked for, and the file it was told to speak into. The two travel
 * together because the pair IS the dispatch — a command with no log is a review nobody can read, and two
 * agents sharing one log is two reviews nobody can tell apart.
 */
final readonly class Launch
{
    public function __construct(
        public string $command,
        public string $log,
    ) {}
}
