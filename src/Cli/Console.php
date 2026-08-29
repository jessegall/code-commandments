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
     * What a command answers when it declines to act — distinct from the 2 a malformed invocation
     * answers, which is a caller that got the command wrong rather than an answer of no.
     */
    public const int REFUSED = 1;

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

    /**
     * Print each line, and answer non-zero — a command DECLINING to act. Everything a script chains
     * behind one hangs on this: `build claim <item> --by=me && <work>` proceeds after a refused claim
     * unless the refusal SAYS it refused, and a refusal only a person can see is not a refusal.
     */
    public function refuse(string ...$lines): int
    {
        $this->say(...$lines);

        return self::REFUSED;
    }
}
