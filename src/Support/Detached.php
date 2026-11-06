<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

/**
 * Starting a command and letting go of it: a hook runs INSIDE the tool call it answers, so what it
 * launches has its stdin closed (a child inheriting a terminal's stdin waits on it, and the hook then
 * waits on the child) and its output sent to a file. Not final — it is the seam a test replaces, since
 * a dispatch nothing can READ is a dispatch nothing can check.
 */
class Detached
{
    /**
     * Run $command in the background, appending everything it says to $log. Returns at once, whatever
     * the command goes on to do.
     */
    public function start(string $command, string $log): void
    {
        exec(sprintf('nohup sh -c %s < /dev/null >> %s 2>&1 &', escapeshellarg($command), escapeshellarg($log)));
    }
}
