<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Journal;

use JesseGall\CodeCommandments\Cli\Console;
use JesseGall\CodeCommandments\Cli\Text;
use JesseGall\PhpTypes\Option;

/**
 * Reading a journal by hand. A session's conversation runs to tens of thousands of characters, so handing
 * it over whole is not reading but flooding — a person wants the last stretch, or the pins, or the one
 * thing they half remember — so this asks what they want, a page at a time, and keeps asking. It opens
 * for a PERSON alone ({@see isForAPerson}); anything else gets one answer and an exit, because a menu
 * nobody can answer is a hang.
 */
final class Menu
{
    /**
     * How many lines are shown before pausing. Just under a short terminal, so the prompt is always on
     * screen with its page rather than scrolled off the top of it.
     */
    private const int PAGE = 30;

    /**
     * How many messages "the last stretch" is — enough to pick up where you left off, short enough to read.
     */
    private const int RECENT = 25;

    public function __construct(
        private readonly Sessions $sessions,
        private readonly string $root,
        private readonly Console $console = new Console,
    ) {}

    /**
     * Is there a PERSON here to answer? Two things must hold, and they fail differently: a terminal on both
     * ends, or there is nobody to read the menu or type into it; and no session id in the environment,
     * which the harness exports into every shell it runs — so a command an AGENT ran is recognised as one
     * even when it happens to have inherited a terminal.
     */
    public static function isForAPerson(): bool
    {
        return stream_isatty(STDIN)
            && stream_isatty(STDOUT)
            && (getenv('CLAUDE_CODE_SESSION_ID') ?: '') === '';
    }

    public function run(): int
    {
        $session = $this->sessions->current();

        while ($session->isNone()) {
            $session = $this->choose();

            if ($session->isNone()) {
                return 0;
            }
        }

        $chosen = $session->unwrap();

        while (true) {
            $answer = $this->ask($this->menu($chosen));

            if ($answer === 'q' || $answer === '') {
                return 0;
            }

            $next = $this->act($chosen, $answer);

            foreach ($next as $other) {
                $chosen = $other;
            }
        }
    }

    /**
     * Carry out one choice, answering with a different session when the reader picked one.
     *
     * @return Option<Session>
     */
    private function act(Session $session, string $answer): Option
    {
        $reading = new Reading($session, $this->root);

        match ($answer) {
            '1' => $this->page($reading->recent(self::RECENT)),
            '2' => $this->page($reading->since(0)),
            '3' => $this->page($reading->since(1)),
            '4' => $this->page($reading->said()),
            '5' => $this->page($reading->pinned()),
            '6' => $this->page($reading->open()),
            '7' => $this->page($reading->mentioning($this->ask('  search for: '))),
            '8' => null,
            default => $this->console->say('  (not an option)'),
        };

        return $answer === '8' ? $this->choose() : Option::none();
    }

    private function menu(Session $session): string
    {
        $name = substr($session->id, 0, 8) . '  ' . ($session->name === '' ? '(nothing said yet)' : $session->name);

        $rule = Text::heading('journal');

        return <<<TEXT
            {$rule}
              {$name}

              1  the last few messages          5  pinned facts
              2  since the last compaction      6  work left open
              3  one compaction further back    7  search
              4  the user's words only          8  another session

              q  quit
            >\x20
            TEXT;
    }

    /**
     * Show $text a page at a time, so a long stretch is read rather than scrolled past.
     */
    private function page(string $text): void
    {
        $lines = explode("\n", rtrim($text));

        if ($text === '') {
            $this->console->say('  (nothing to show)');

            return;
        }

        foreach (array_chunk($lines, self::PAGE) as $at => $page) {
            if ($at > 0 && $this->ask('  ── more? (enter, or q) ') === 'q') {
                return;
            }

            $this->console->say(...$page);
        }
    }

    /**
     * @return Option<Session>
     */
    private function choose(): Option
    {
        $all = $this->sessions->all();

        if ($all === []) {
            $this->console->say('No sessions recorded for this project yet.');

            return Option::none();
        }

        $this->console->say('');

        foreach ($all as $at => $session) {
            $this->console->say(sprintf('  %2d  %s', $at + 1, $session->describe()));
        }

        $picked = $all[((int) $this->ask("\n  which? ")) - 1] ?? null;

        if ($picked === null) {
            return Option::none();
        }

        $this->sessions->mount($picked);

        return Option::some($picked);
    }

    private function ask(string $prompt): string
    {
        fwrite(STDOUT, $prompt);

        return strtolower(trim((string) fgets(STDIN)));
    }





}
