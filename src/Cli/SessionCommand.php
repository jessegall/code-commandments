<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli;

use JesseGall\CodeCommandments\Cli\Help\Help;
use JesseGall\CodeCommandments\Cli\Session\Session;
use JesseGall\CodeCommandments\Cli\Session\Sessions;
use JesseGall\CodeCommandments\Hooks\HookIO;
use JesseGall\CodeCommandments\Support\Directory;
use JesseGall\CodeCommandments\Workspace;
use JesseGall\PhpTypes\Option;

/**
 * `commandments session` — where this session keeps its state. Everything session-scoped (the
 * the sins checklist, the plan marker, the stop gate, every hook counter) lives in one folder named by a
 * hash of the session id, which is deliberately unguessable from the outside — so the way to find it is
 * to ask, not to work it out.
 */
final class SessionCommand implements Command
{
    public function __construct(
        private readonly HookIO $io = new HookIO,
        private readonly Console $console = new Console,
    ) {}

    public function names(): array
    {
        return ['session'];
    }

    public function help(): Help
    {
        return Help::of("Where this session keeps its state — the folder holding its checklist, plan marker and stop gate.")
            ->form('session', 'print the folder, and what is in it')
            ->form('session list', "every session folder this project has, newest first — what a SECOND terminal asks, having no session of its own. An ORPHAN, a folder no session and no name points at any more, is marked as one")
            ->form('session --path', 'print only the path, for piping somewhere')
            ->form('session name "<name>"', 'NAME this session — the folder is renamed to match in every checkout, so it is one you can come back to')
            ->form('session forget "<name>"', 'drop a name; its session answers to its hash again')
            ->form('session adopt <folder>', 'take a stranded folder INTO this session — everything it holds is moved, and nothing that did not come across is deleted')
            ->option('--path', 'the bare path and nothing else')
            ->option('--into', 'the folder to adopt INTO, for a terminal with no session of its own')
            ->note('Run it from inside Claude Code by typing `!vendor/bin/commandments session` at the prompt: '
                . "the `!` prefix runs a shell command in the session, so the answer lands in the conversation "
                . 'without costing a turn of thinking. From any other terminal, `session list` is the one to use — '
                . 'a shell outside the harness has no session of its own to report.')
            ->note('`adopt` acts on the CHECKOUT it is run from, because worktree-scoped state belongs to its '
                . 'worktree — so a folder `list` shows under a worktree is adopted from inside that worktree.');
    }

    public function run(Input $input): int
    {
        $root = Workspace::ofSession($this->io->projectRoot())->root();

        return match ($input->firstArgument()->unwrapOr('')) {
            'list' => $this->list($root),
            'name' => $this->name($root, $input->argument(1)->unwrapOr('')),
            'forget' => $this->forget($root, $input->argument(1)->unwrapOr('')),
            'adopt' => $this->adopt($input->argument(1)->unwrapOr(''), $input->option('into')->unwrapOr('')),
            default => $this->show($root, $input),
        };
    }

    /**
     * Give this session a name. The folder is RENAMED to match, because a five-character hash is not
     * something anybody comes back to — and a session now holds its own plan, so coming back to it is
     * the point. The map records which id the name belongs to, so an agent still finds its own folder
     * from the id it was handed.
     */
    private function name(string $root, string $name): int
    {
        if ($name === '') {
            return $this->console->refuse('Say what to call it: `commandments session name "<name>"`.');
        }

        $id = getenv('CLAUDE_CODE_SESSION_ID') ?: '';

        if ($id === '') {
            return $this->console->refuse('No session to name — a shell outside the harness has none of its own.');
        }

        $was = $this->foldersOf($root, $id);
        $names = Workspace::at($root)->names();

        if (! $names->name($id, $name)) {
            return $this->console->refuse("`{$name}` already belongs to another session.");
        }

        $this->follow($was, $id);

        return $this->console->say("▸ This session is `{$name}`.", '  ' . Workspace::at($root, $id)->sessionDir());
    }

    /**
     * Drop a name, returning its session to its hash. The folders move back with it, so the name and the
     * directories can never disagree.
     */
    private function forget(string $root, string $name): int
    {
        $names = Workspace::at($root)->names();

        foreach ($names->idOf($name) as $id) {
            $was = $this->foldersOf($root, $id);
            $names->forget($name);
            $this->follow($was, $id);

            return $this->console->say("▸ `{$name}` is forgotten.", '  ' . Workspace::at($root, $id)->sessionDir());
        }

        return $this->console->refuse("No session is called `{$name}`.");
    }

    /**
     * The folder $id occupies in EVERY checkout of this repository, keyed by the checkout. A session is
     * one thing across its worktrees while its state is not — a lane keeps its own plan and counters —
     * so a rename that moved only the main checkout's folder would leave a lane writing under the name
     * the session no longer answers to.
     *
     * @return array<string, string>
     */
    private function foldersOf(string $root, string $id): array
    {
        $folders = [];

        foreach ([$root, ...$this->io->git()->worktrees($root)] as $checkout) {
            $folders[$checkout] = Workspace::at($checkout, $id)->sessionDir();
        }

        return $folders;
    }

    /**
     * Move each checkout's folder to where $id's state now belongs. A destination that already exists is
     * ADOPTED rather than overwritten — a rename onto a non-empty directory fails, and the half that
     * loses is a stretch of the record.
     *
     * @param  array<string, string>  $was
     */
    private function follow(array $was, string $id): void
    {
        foreach ($was as $checkout => $from) {
            $to = Workspace::at($checkout, $id)->sessionDir();

            if ($from === $to || ! is_dir($from)) {
                continue;
            }

            if (is_dir($to)) {
                Adoption::take($from, $to);

                continue;
            }

            rename($from, $to);
        }
    }

    /**
     * Take a stranded folder into the one this session reads. It acts on the CHECKOUT it is run from:
     * worktree-scoped state belongs to its worktree, so adopting a lane's orphan into the main checkout
     * would move it somewhere nothing reads for a second time.
     */
    private function adopt(string $folder, string $into): int
    {
        if ($folder === '') {
            return $this->console->refuse('Say which folder to adopt: `commandments session adopt <folder>`.', '  `session list` marks the orphans.');
        }

        $workspace = Workspace::at($this->io->projectRoot());
        $from = $workspace->sessionDirNamed($folder);

        if (! is_dir($from)) {
            return $this->console->refuse("No folder called `{$folder}` here.", '  `session list` names every folder, and marks the orphans.');
        }

        foreach ($this->destination($workspace, $into) as $to) {
            return $from === $to
                ? $this->console->refuse("`{$folder}` is the folder this session already reads.")
                : $this->report($folder, Adoption::take($from, $to), $to);
        }

        return $this->console->refuse(
            'No session to adopt into — a shell outside the harness has none of its own.',
            '  Name the destination: `commandments session adopt ' . $folder . ' --into=<folder>`.',
        );
    }

    /**
     * Where an adoption lands — the folder $into names, else this session's own. Absent for a terminal
     * outside the harness that named neither, which is the one case with no answer to guess at.
     *
     * @return Option<string>
     */
    private function destination(Workspace $workspace, string $into): Option
    {
        if ($into !== '') {
            return Option::some($workspace->sessionDirNamed($into));
        }

        $id = getenv('CLAUDE_CODE_SESSION_ID') ?: '';

        return $id === ''
            ? Option::none()
            : Option::some(Workspace::at($workspace->root(), $id)->sessionDir());
    }

    /**
     * What came across, and what did not. An incomplete adoption answers non-zero: the folder is still
     * standing and still holds a stretch of the record, which is a thing to act on rather than read past.
     */
    private function report(string $folder, Adoption $adoption, string $to): int
    {
        $entries = $adoption->count() === 1 ? '1 entry' : $adoption->count() . ' entries';
        $lines = ["▸ `{$folder}` — {$entries} adopted into", '  ' . $to];

        foreach ($adoption->moved() as $entry) {
            $lines[] = "  moved    {$entry}";
        }

        if ($adoption->isComplete()) {
            $lines[] = "  `{$folder}` is gone; nothing was left behind.";

            return $this->console->say(...$lines);
        }

        foreach ($adoption->kept() as $entry) {
            $lines[] = "  KEPT     {$entry}  (the destination already has one; nothing was deleted)";
        }

        $lines[] = "  `{$folder}` still stands. Merge what is left by hand, then adopt again.";

        return $this->console->refuse(...$lines);
    }

    private function show(string $root, Input $input): int
    {
        $dir = Workspace::at($root)->sessionDir();

        if ($input->hasFlag('path')) {
            return $this->console->say($dir);
        }

        $this->console->say($dir, '');

        if (! is_dir($dir)) {
            return $this->console->say('  (nothing written yet — it appears the first time something records state)');
        }

        foreach ($this->contents($dir) as $line) {
            $this->console->say($line);
        }

        return 0;
    }

    /**
     * Every session folder this project has, newest first. What a terminal OUTSIDE the harness asks: it
     * has no session id of its own, so naming one folder would only ever name the wrong one.
     */
    private function list(string $root): int
    {
        // A folder is named after a HASH of the session id, so it looks nothing like the id the
        // transcripts are called after. Both are printed together, or a reader holding one of them has no
        // way to reach the other.
        $named = [];

        foreach (Sessions::of(Workspace::at($root))->all() as $session) {
            // Keyed by the folder the session ACTUALLY occupies, which is its NAME once it has one.
            // Asking for the derived hash instead left every renamed session unable to find its own
            // transcript — and a named session is precisely one somebody means to come back to.
            $named[Workspace::at($root, $session->id)->sessionKey()] = $session;
        }

        $lines = [];

        // Every CHECKOUT, because a lane keeps its own counters and plan under the same session — and a
        // folder listed nowhere is one nobody can adopt and anybody can delete.
        foreach ([$root, ...$this->io->git()->worktrees($root)] as $checkout) {
            $folders = $this->folders($checkout, $named, Workspace::at($root)->names()->all());

            if ($folders === []) {
                continue;
            }

            if ($checkout !== $root) {
                $lines[] = "  in {$checkout}:";
            }

            $lines = [...$lines, ...$folders];
        }

        if ($lines === []) {
            return $this->console->say('No session has recorded anything for this project yet.');
        }

        return $this->console->say(...$lines);
    }

    /**
     * One checkout's session folders, newest first. A folder no transcript and no name accounts for is an
     * ORPHAN — nothing can reach it from a session id, so it reads exactly like a live session to anybody
     * tidying up, and what it holds is a stretch of the record.
     *
     * @param  array<string, Session>  $named  folder key → the session that occupies it
     * @param  array<string, string>  $names  name → session id
     * @return list<string>
     */
    private function folders(string $checkout, array $named, array $names): array
    {
        $dirs = array_filter(Directory::newestFirst(Workspace::at($checkout)->sessionsDir()), is_dir(...));

        return array_map(function (string $dir) use ($named, $names) {
            $key = basename($dir);
            $session = $named[$key] ?? null;

            return sprintf(
                '  %-8s %-10s %s  %s',
                $key,
                $session?->id === null ? '' : substr($session->id, 0, 8),
                date('Y-m-d H:i', filemtime($dir) ?: 0),
                $this->describe($key, $session, $names),
            );
        }, array_values($dirs));
    }

    /**
     * What a folder IS, in the one column a reader scans.
     *
     * @param  array<string, string>  $names
     */
    private function describe(string $key, ?Session $session, array $names): string
    {
        if ($session !== null) {
            // A transcript names itself from its first exchange, so a session that has barely started has
            // no name yet — and a blank column reads as a folder nothing knows about, which is the one
            // thing this column exists to tell apart.
            return $session->name === '' ? '(nothing said yet)' : $session->name;
        }

        if ($key === Workspace::DEFAULT_SESSION || array_key_exists($key, $names)) {
            return '(no transcript found)';
        }

        return "(ORPHAN — nothing points here; `session adopt {$key}`)";
    }

    /**
     * What the folder holds, largest last-written first, each with its size — enough to see at a glance
     * which parts of the session have actually recorded anything.
     *
     * @return list<string>
     */
    private function contents(string $dir): array
    {
        // Most of what a session keeps is a DOTFILE (`.until`, `.plan-active`), which `glob`
        // skips — so the folder would read as empty exactly when it is fullest.
        return array_map(
            fn (string $file) => sprintf('  %-24s %6s  %s', basename($file), $this->size($file), date('H:i', filemtime($file) ?: 0)),
            Directory::newestFirst($dir),
        );
    }

    private function size(string $file): string
    {
        $bytes = (int) @filesize($file);

        return $bytes < 1024 ? "{$bytes}B" : round($bytes / 1024, 1) . 'K';
    }

}
