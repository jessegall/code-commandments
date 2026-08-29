<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli;

/**
 * Reading what someone types. A menu is a set of buttons, so choosing from one costs a single tap ({@see
 * key}) — a terminal withholds the keystroke only because it is line-buffered by default, which is a
 * setting rather than a fact — while a search term is not a button and is typed in full ({@see line}).
 * Without a terminal, a keypress is read as a line: a stream has no mode to put into.
 */
final class Keyboard
{
    /**
     * @param  resource  $input
     */
    public function __construct(
        private $input = STDIN,
        private readonly Console $console = new Console,
    ) {}

    /**
     * One keypress, unbuffered and echoed back so the reader sees what they chose. Falls back to a whole
     * line where the input is not a terminal.
     */
    public function key(string $prompt): string
    {
        $this->console->write($prompt);

        if (! $this->isTerminal()) {
            return $this->read();
        }

        $settings = $this->mode('-icanon -echo min 1 time 0');

        // A terminal left unbuffered outlives this process and breaks the shell that started it, so the
        // restore is registered BEFORE the read — a Ctrl-C lands in the middle of it, not politely after.
        register_shutdown_function(fn () => $this->mode($settings));

        $key = strtolower((string) fread($this->input, 1));
        $this->mode($settings);

        $this->console->say(trim($key));

        return trim($key);
    }

    /**
     * A whole typed answer, ended with Enter — what a search term is, and what no keypress can be.
     */
    public function line(string $prompt): string
    {
        $this->console->write($prompt);

        return $this->read();
    }

    private function read(): string
    {
        return strtolower(trim((string) fgets($this->input)));
    }

    /**
     * Put the terminal into $settings, answering with what it was — so the caller can hand back exactly
     * what it borrowed. A terminal left in raw mode outlives the process and breaks the user's shell.
     */
    private function mode(string $settings): string
    {
        $was = trim((string) @shell_exec('stty -g 2>/dev/null'));

        @shell_exec('stty ' . $settings . ' 2>/dev/null');

        return $was;
    }

    /**
     * Is this a terminal whose mode can actually be changed? Both halves are asked: a stream that is not a
     * terminal has no mode, and a terminal `stty` cannot read is one this process does not control — a
     * pseudo-terminal it inherited, say — where switching the mode would block on a keystroke that is
     * never coming.
     */
    private function isTerminal(): bool
    {
        return stream_isatty($this->input) && trim((string) @shell_exec('stty -g 2>/dev/null')) !== '';
    }
}
