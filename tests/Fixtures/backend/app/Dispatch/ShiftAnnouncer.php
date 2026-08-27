<?php

namespace Shop\Dispatch;

use JesseGall\CodeCommandments\Sins\Backend\FlagArgument;
use JesseGall\CodeCommandments\Testing\Fixed;
use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Labels\PrintLog;

/**
 * Tells the floor staff what is happening.
 */
final class ShiftAnnouncer
{
    public function __construct(private readonly PrintLog $log) {}

    /**
     * Two announcements sharing a name. At the call site this reads `announce($msg, true)` — true
     * what? Nobody can tell without opening this method.
     */
    #[Sinful(FlagArgument::class)]
    public function announce(string $message, bool $urgent): void
    {
        if ($urgent) {
            $this->log->record('SIREN ' . strtoupper($message));
        } else {
            $this->log->record($message);
        }
    }

    /**
     * The same two announcements, named. The call site now says which one it wanted, and each half
     * is free to grow its own parameters without the other having to ignore them.
     */
    #[Fixed(FlagArgument::class)]
    #[Righteous(FlagArgument::class)]
    public function announceUrgently(string $message): void
    {
        $this->log->record('SIREN ' . strtoupper($message));
    }

    #[Fixed(FlagArgument::class)]
    public function announceQuietly(string $message): void
    {
        $this->log->record($message);
    }
}
