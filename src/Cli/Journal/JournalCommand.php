<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Journal;

use JesseGall\CodeCommandments\Cli\Command;
use JesseGall\CodeCommandments\Cli\Console;
use JesseGall\CodeCommandments\Cli\Help\Help;
use JesseGall\CodeCommandments\Cli\Input;
use JesseGall\CodeCommandments\Hooks\HookIO;
use JesseGall\CodeCommandments\Workspace;
use JesseGall\PhpTypes\Option;

/**
 * `commandments journal` — what a compaction took. The summary it leaves keeps what was DONE and loses
 * what was DECIDED, so this reads the session's own transcript, which lost nothing, and shows the parts
 * worth reading: the user's words in full, enough either side of them to know what they answered, and the
 * agent's own tagged messages through the stretches it worked alone.
 */
final class JournalCommand implements Command
{
    public function __construct(
        private readonly HookIO $io = new HookIO,
        private readonly Console $console = new Console,
    ) {}

    public function names(): array
    {
        return ['journal'];
    }

    public function help(): Help
    {
        return Help::of('What a compaction took — the decisions, corrections and unfinished work a summary drops, read back out of the session transcript.')
            ->form('journal', 'the conversation since the last compaction (or the session menu, if you have not picked one)')
            ->form('journal --back=N', 'N compactions further back — `--back=1` is the stretch the last summary replaced')
            ->form('journal user', "only the user's own words, in full")
            ->form('journal search "<term>"', 'every line mentioning it, so you can find where a thing was decided')
            ->form('journal remember "<fact>"', 'pin a fact — it survives every compaction and is written into the summariser\'s own instructions')
            ->form('journal pinned', 'every pinned fact still standing')
            ->form('journal open', 'work started and never finished — the live state a compaction must carry')
            ->form('journal instructions', 'the brief — how to tag, what to pin, and how to read it back. Every refusal points here')
            ->form('journal sessions', 'the sessions of this project, newest first')
            ->form('journal use <id>', 'read that session from now on (a prefix of the id is enough)')
            ->option('--back=N', 'how many compactions back to read (default 0, the current stretch)')
            ->note('A hook always knows which session it is in; a human does not, so `journal sessions` lists them '
                . 'and `journal use <id>` MOUNTS one — every later command reads that session until you choose '
                . 'another. The list is built from the transcripts themselves, so a session that ran before any '
                . 'of this existed can still be read back.');
    }

    public function run(Input $input): int
    {
        $workspace = Workspace::at($this->io->projectRoot());
        $sessions = Sessions::of($workspace);

        return match ($input->firstArgument()->unwrapOr('read')) {
            'instructions', 'brief', 'help' => $this->console->say(new Brief($workspace->root())->render()),
            'sessions', 'list' => $this->sessions($sessions),
            'use', 'mount' => $this->use($sessions, $input->argument(1)->unwrapOr('')),
            'remember', 'pin' => $this->remember($workspace, $this->text($input, from: 1)),
            'pinned' => $this->pinned($workspace),
            'open' => $this->open($workspace),
            'user' => $this->read($sessions, $input, onlyTheUser: true),
            'search', 'find' => $this->search($sessions, $this->text($input, from: 1)),
            default => $this->read($sessions, $input, onlyTheUser: false),
        };
    }

    /**
     * The sessions of this project, so a human can see which one they mean.
     */
    private function sessions(Sessions $sessions): int
    {
        $all = $sessions->all();

        if ($all === []) {
            return $this->console->say('No sessions recorded for this project yet.');
        }

        $mounted = $sessions->mounted()->mapOr('', fn (Session $session) => $session->id);

        foreach ($all as $session) {
            $mark = $session->id === $mounted ? '▸' : ' ';
            $this->console->say(sprintf('%s %s  %s', $mark, substr($session->id, 0, 8), $session->describe()));
        }

        return $this->console->say('', 'Read one with `commandments journal use <id>` — the first few characters are enough.');
    }

    private function use(Sessions $sessions, string $handle): int
    {
        foreach ($sessions->named($handle) as $session) {
            $sessions->mount($session);

            return $this->console->say('▸ Reading ' . substr($session->id, 0, 8) . '  ' . $session->describe());
        }

        return $this->console->say("No session here answers to '{$handle}'.", 'Run `commandments journal sessions` to see them.');
    }

    /**
     * The conversation itself — the part of it worth reading.
     */
    private function read(Sessions $sessions, Input $input, bool $onlyTheUser): int
    {
        foreach ($this->chosen($sessions) as $session) {
            $back = $input->option('back')->mapOr(0, intval(...));
            $lines = $session->transcript()->chunk($back);

            if ($onlyTheUser) {
                $lines = array_values(array_filter($lines, fn (Line $line) => $line->isPrompt() && $line->text !== ''));
            }

            return $this->console->say($this->heading($session, $back), '', new Digest($lines)->render());
        }

        return $this->sessions($sessions);
    }

    private function search(Sessions $sessions, string $term): int
    {
        if ($term === '') {
            return $this->console->say('Say what to look for: `commandments journal search "<term>"`.');
        }

        foreach ($this->chosen($sessions) as $session) {
            $found = [];

            foreach ($session->transcript()->lines() as $line) {
                if ($line->isSpeech() && $line->mentions($term)) {
                    $found[] = $line;
                }
            }

            return $found === []
                ? $this->console->say("Nothing in this session mentions '{$term}'.")
                : $this->console->say(new Digest($found)->render());
        }

        return $this->sessions($sessions);
    }

    private function remember(Workspace $workspace, string $fact): int
    {
        if (trim($fact) === '') {
            return $this->console->say('Say what to remember: `commandments journal remember "<fact>"`.');
        }

        Journal::inSession($workspace)->file(new Entry(
            Kind::Mark,
            gmdate('Y-m-d\TH:i:s\Z'),
            '',
            '',
            Option::some(Tag::Pinned),
            $fact,
        ));

        return $this->console->say('✓ Pinned. It survives every compaction, and rides in the summariser\'s own instructions.');
    }

    private function pinned(Workspace $workspace): int
    {
        $pinned = Journal::inSession($workspace)->pinned();

        if ($pinned === []) {
            return $this->console->say('Nothing pinned yet — `commandments journal remember "<fact>"` pins one.');
        }

        foreach ($pinned as $entry) {
            $this->console->say('  • ' . $entry->text);
        }

        return 0;
    }

    private function open(Workspace $workspace): int
    {
        $open = Journal::inSession($workspace)->openSpans();

        if ($open === []) {
            return $this->console->say('No work left open.');
        }

        foreach ($open as $entry) {
            $this->console->say('  • ' . $entry->text);
        }

        return 0;
    }

    /**
     * The session being read, or none — which is the moment to show the menu rather than guess.
     *
     * @return Option<Session>
     */
    private function chosen(Sessions $sessions): Option
    {
        return $sessions->current();
    }

    private function heading(Session $session, int $back): string
    {
        $chunk = $back === 0 ? 'since the last compaction' : "{$back} compaction(s) back";

        return sprintf('── %s · %s %s', substr($session->id, 0, 8), $chunk, str_repeat('─', 30));
    }

    /**
     * The words after argument $from, joined — a condition or a fact the user typed unquoted.
     */
    private function text(Input $input, int $from): string
    {
        return trim(implode(' ', array_slice($input->arguments(), $from)));
    }

}
