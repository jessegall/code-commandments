<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Journal;

use JesseGall\CodeCommandments\Cli\Command;
use JesseGall\CodeCommandments\Cli\Console;
use JesseGall\CodeCommandments\Cli\Text;
use JesseGall\CodeCommandments\Cli\Help\Help;
use JesseGall\CodeCommandments\Cli\Input;
use JesseGall\CodeCommandments\Hooks\HookIO;
use JesseGall\CodeCommandments\Cli\State\StateFile;
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
    /**
     * How many near misses a failed lookup offers back. Enough to recognise the one you meant, few enough
     * that the answer is still an answer rather than the list you were trying to avoid reading.
     */
    private const int SUGGESTED = 3;

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
            ->form('journal', 'a MENU when a person runs it at a terminal — read the last stretch, the pins, the open work, or search. Anywhere else, the conversation since the last compaction')
            ->form('journal --back=N', 'N compactions further back — `--back=1` is the stretch the last summary replaced')
            ->form('journal user', "only the user's own words, in full")
            ->form('journal search "<term>"', 'every line mentioning it, so you can find where a thing was decided')
            ->form('journal remember "<fact>"', 'pin a fact — it survives every compaction and is written into the summariser\'s own instructions')
            ->form('journal remember "<fact>" --supersedes=<n>', 'pin a fact that CORRECTS pin <n> — the old one is kept and marked, and only the new one is carried forward')
            ->form('journal pins [--last=N]', 'every pinned fact, numbered — the number is what `--supersedes` takes, and a superseded one is shown struck')
            ->form('journal agents', 'which WORKERS of this session kept a record, and how much each said')
            ->form('journal open', 'work started and never finished — the live state a compaction must carry')
            ->form('journal verify', "does the record agree with what you SAID? names every tag the journal never filed — the one thing you cannot check from the inside")
            ->form('journal instructions', 'the brief — how to tag, what to pin, and how to read it back. Every refusal points here')
            ->form('journal sessions', 'the sessions of this project, newest first')
            ->form('journal use <id>', 'read that session from now on (a prefix of the id is enough)')
            ->option('--back=N', 'how many compactions back to read (default 0, the current stretch)')
            ->option('--last=N', 'on `pins`, show only the most recent N — a long list is tailed, and the middle of it is what gets missed')
            ->option('--supersedes=N', 'on `remember`, the pin this fact replaces. Nothing is deleted: pin N stays in the record marked as superseded, the new pin names it, and only the new one reaches a compacted reader')
            ->option('--full', 'the whole stretch, unbounded — by default a reading is cut to fit, worst first, so it does not spend the context it exists to restore')
            ->note('A pin promises to survive every compaction, so it is what an agent reaches for whenever it is '
                . 'afraid of losing something — which fills the record with facts that were true when written and '
                . 'are not now. Correcting one never DELETES it: `remember "<the fact now>" --supersedes=<n>` keeps '
                . 'the old pin readable, marks it, and stops carrying it forward. `journal pins` is where the '
                . 'numbers are.')
            ->note('A hook always knows which session it is in; a human does not, so `journal sessions` lists them '
                . 'and `journal use <id>` MOUNTS one — every later command reads that session until you choose '
                . 'another. The list is built from the transcripts themselves, so a session that ran before any '
                . 'of this existed can still be read back.');
    }

    public function run(Input $input): int
    {
        $workspace = Workspace::ofSession($this->io->projectRoot());
        $sessions = Sessions::of($workspace);

        if ($input->firstArgument()->isNone() && Menu::isForAPerson()) {
            return new Menu($sessions, $workspace->root(), $this->console)->run();
        }

        return match ($input->firstArgument()->unwrapOr('read')) {
            'instructions', 'brief', 'help' => $this->console->say(new Brief($workspace->root())->render()),
            'sessions', 'list' => $this->sessions($sessions),
            'use', 'mount' => $this->use($sessions, $input, $input->argument(1)->unwrapOr('')),
            'remember', 'pin' => $this->remember($workspace, $input),
            'pins', 'pinned' => $this->pinned($sessions, $input),
            'open' => $this->open($sessions),
            'agents', 'workers' => $this->agents($workspace),
            'verify', 'check' => $this->verify($sessions),
            'user' => $this->read($sessions, $input, onlyTheUser: true),
            'search', 'find' => $this->search($sessions, $this->text($input, from: 1)),
            default => $this->read($sessions, $input, onlyTheUser: false),
        };
    }

    /**
     * The WORKERS of this session that kept a record, and how much each said. A worker's reasoning is
     * available when somebody goes looking rather than spilled into the orchestrator's own journal,
     * where it would drown the thing they came to find.
     */
    private function agents(Workspace $workspace): int
    {
        $lines = [];

        foreach (glob($workspace->sessionDir() . '/agents/*/.journal') ?: [] as $file) {
            $agent = basename(dirname($file));
            $said = count(new Journal(new StateFile($file, Journal::legend()))->entries());

            $lines[] = sprintf('  %-20s %d entr%s', $agent, $said, $said === 1 ? 'y' : 'ies');
        }

        if ($lines === []) {
            return $this->console->say('No worker has kept a record in this session.');
        }

        return $this->console->say(
            ...$lines,
            ...['', '  `commandments journal use <agent-id>` reads one out.'],
        );
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

    /**
     * Mount a session AND read it. Choosing one is never the thing a person wanted — reading it is — so a
     * `use` that only confirmed the choice would announce a reading it had not done and leave them to ask
     * a second time for the answer they came for.
     */
    private function use(Sessions $sessions, Input $input, string $handle): int
    {
        foreach ($sessions->named($handle) as $session) {
            $sessions->mount($session);
            $this->console->say('▸ ' . substr($session->id, 0, 8) . '  ' . $session->describe(), '');

            return $this->show($session, $input, onlyTheUser: false);
        }

        return $this->missing($sessions, $handle);
    }

    /**
     * Nothing answered to $handle. A hash is unreadable by eye, so the likeliest reason is a character
     * misread off the screen — which makes the near misses worth more than the fact of the failure.
     */
    private function missing(Sessions $sessions, string $handle): int
    {
        $this->console->say("No session here answers to '{$handle}'.");

        $near = array_filter($sessions->all(), fn (Session $session) => $session->resembles($handle));

        if ($near === []) {
            return $this->console->say('Run `commandments journal sessions` to see them.');
        }

        $this->console->say('', 'Did you mean:');

        foreach (array_slice($near, 0, self::SUGGESTED) as $session) {
            $this->console->say('  ' . substr($session->id, 0, 8) . '  ' . $session->describe());
        }

        return 0;
    }

    /**
     * The conversation itself — the part of it worth reading.
     */
    private function read(Sessions $sessions, Input $input, bool $onlyTheUser): int
    {
        foreach ($this->chosen($sessions) as $session) {
            return $this->show($session, $input, $onlyTheUser);
        }

        return $this->sessions($sessions);
    }

    /**
     * One session's conversation, as much of it as is worth reading.
     */
    private function show(Session $session, Input $input, bool $onlyTheUser): int
    {
        $back = $input->option('back')->mapOr(0, intval(...));
        $reading = new Reading($session, Workspace::ofSession($this->io->projectRoot())->root(), $input->hasFlag('full') ? null : Reading::BUDGET);

        return $this->console->say(
            $this->heading($session, $back),
            '',
            $onlyTheUser ? $reading->said() : $reading->since($back),
        );
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

    private function remember(Workspace $workspace, Input $input): int
    {
        $fact = $this->text($input, from: 1);

        if (trim($fact) === '') {
            return $this->console->refuse('Say what to remember: `commandments journal remember "<fact>"`.');
        }

        $journal = Journal::inSession($workspace);
        $supersedes = $input->option('supersedes')->map(intval(...));

        foreach ($supersedes as $number) {
            $refusal = $this->unstrikable($journal, $number);

            if ($refusal !== []) {
                return $this->console->refuse(...$refusal);
            }
        }

        $journal->file(Entry::pin(gmdate('Y-m-d\TH:i:s\Z'), $fact, $supersedes));

        return $this->console->say(...$this->pinnedIt($journal, $supersedes));
    }

    /**
     * Why pin $number cannot be struck, said in full, or nothing when it can. A number that names no pin,
     * or one already corrected, is the reader working from a stale listing — so the refusal says which
     * pin stands now rather than filing a correction against a fact nobody is reading.
     *
     * @return list<string>
     */
    private function unstrikable(Journal $journal, int $number): array
    {
        foreach ($journal->pin($number) as $pin) {
            foreach ($pin->supersededBy as $by) {
                return [
                    "Pin {$number} was already superseded by pin {$by}.",
                    "Strike the one that still stands — `--supersedes={$by}` — or `commandments journal pins` to see them.",
                ];
            }

            return [];
        }

        return [
            "This session has no pin {$number}.",
            'Run `commandments journal pins` — the number in front of each fact is what `--supersedes` takes.',
        ];
    }

    /**
     * What was just pinned, and what became of the pin it replaced. The old one is named as KEPT, because
     * the whole reason to strike rather than delete is that the correction stays readable.
     *
     * @param  Option<int>  $supersedes
     * @return list<string>
     */
    private function pinnedIt(Journal $journal, Option $supersedes): array
    {
        $number = count($journal->pins());

        return $supersedes->mapOr(
            ["✓ Pinned as {$number}. It survives every compaction, and rides in the summariser's own instructions."],
            fn (int $struck) => [
                "✓ Pinned as {$number}, superseding pin {$struck}.",
                "Pin {$struck} is kept and marked — `commandments journal pins` still shows it — and from now on only {$number} is carried across a compaction.",
            ],
        );
    }

    private function pinned(Sessions $sessions, Input $input): int
    {
        foreach ($this->chosen($sessions) as $session) {
            $pinned = new Reading($session, Workspace::ofSession($this->io->projectRoot())->root())
                ->pinned($input->option('last')->map(intval(...))->unwrapOr(null));

            return $this->console->say($pinned === '' ? 'Nothing pinned yet — `commandments journal remember "<fact>"` pins one.' : $pinned);
        }

        return $this->sessions($sessions);
    }

    /**
     * Does the record agree with what was said? The one question an agent cannot answer from the inside.
     */
    private function verify(Sessions $sessions): int
    {
        foreach ($this->chosen($sessions) as $session) {
            return $this->console->say(
                new Reading($session, Workspace::ofSession($this->io->projectRoot())->root())->verify(),
            );
        }

        return $this->sessions($sessions);
    }

    private function open(Sessions $sessions): int
    {
        foreach ($this->chosen($sessions) as $session) {
            $open = new Reading($session, Workspace::ofSession($this->io->projectRoot())->root())->open();

            return $this->console->say($open === '' ? 'No work left open.' : $open);
        }

        return $this->sessions($sessions);
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

        return Text::heading(substr($session->id, 0, 8) . ' · ' . $chunk);
    }

    /**
     * The words after argument $from, joined — a condition or a fact the user typed unquoted.
     */
    private function text(Input $input, int $from): string
    {
        return trim(implode(' ', array_slice($input->arguments(), $from)));
    }

}
