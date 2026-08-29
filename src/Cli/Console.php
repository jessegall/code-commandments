<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli;

/**
 * Writing to the terminal — the one place a command puts words on screen. Every {@see Command} ends by
 * saying something and returning an exit code, so {@see say} does both: the lines, then the code the
 * caller was going to return anyway.
 */
final class Console
{
    /**
     * @param  resource  $out  where the words go — STDOUT for a person, and anything writable for a test
     */
    public function __construct(private $out = STDOUT) {}

    /**
     * Put $text on screen with no line break after it — what a prompt is, since the answer is typed on the
     * same line.
     */
    public function write(string $text): void
    {
        fwrite($this->out, $text);
    }

    /**
     * Print each line, and answer 0 — a command's whole tail, so `return $this->console->say(...)` reads
     * as the one statement it is.
     */
    public function say(string ...$lines): int
    {
        foreach ($lines as $line) {
            fwrite($this->out, $line . "\n");
        }

        return 0;
    }
}
