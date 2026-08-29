<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli;

use JesseGall\CodeCommandments\Cli\Help\Help;
use JesseGall\CodeCommandments\Cli\Journal\Sessions;
use JesseGall\CodeCommandments\Hooks\HookIO;
use JesseGall\CodeCommandments\Workspace;

/**
 * `commandments session` — where this session keeps its state. Everything session-scoped (the journal,
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
        return Help::of("Where this session keeps its state — the folder holding its journal, checklist, plan marker and stop gate.")
            ->form('session', 'print the folder, and what is in it')
            ->form('session list', "every session folder this project has, newest first — what a SECOND terminal asks, having no session of its own")
            ->form('session --path', 'print only the path, for piping somewhere')
            ->option('--path', 'the bare path and nothing else')
            ->note('Run it from inside Claude Code by typing `!vendor/bin/commandments session` at the prompt: '
                . "the `!` prefix runs a shell command in the session, so the answer lands in the conversation "
                . 'without costing a turn of thinking. From any other terminal, `session list` is the one to use — '
                . 'a shell outside the harness has no session of its own to report.');
    }

    public function run(Input $input): int
    {
        $root = Workspace::ofSession($this->io->projectRoot())->root();

        if ($input->firstArgument()->unwrapOr('') === 'list') {
            return $this->list($root);
        }

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
        $dirs = glob(Workspace::at($root)->dir() . '/sessions/*', GLOB_ONLYDIR) ?: [];

        if ($dirs === []) {
            return $this->console->say('No session has recorded anything for this project yet.');
        }

        usort($dirs, fn (string $a, string $b) => (filemtime($b) ?: 0) <=> (filemtime($a) ?: 0));

        // A folder is named after a HASH of the session id, so it looks nothing like the id the
        // transcripts are called after. Both are printed together, or a reader holding one of them has no
        // way to reach the other.
        $named = [];

        foreach (Sessions::of(Workspace::at($root))->all() as $session) {
            $named[$session->key()] = $session;
        }

        foreach ($dirs as $dir) {
            $key = basename($dir);
            $session = $named[$key] ?? null;

            $this->console->say(sprintf(
                '  %-8s %-10s %s  %s',
                $key,
                $session?->id === null ? '' : substr($session->id, 0, 8),
                date('Y-m-d H:i', filemtime($dir) ?: 0),
                $session?->name ?? '(no transcript found)',
            ));
        }

        return 0;
    }

    /**
     * What the folder holds, largest last-written first, each with its size — enough to see at a glance
     * which parts of the session have actually recorded anything.
     *
     * @return list<string>
     */
    private function contents(string $dir): array
    {
        // Most of what a session keeps is a DOTFILE (`.journal`, `.until`, `.plan-active`), which `glob`
        // skips — so the folder would read as empty exactly when it is fullest.
        $files = array_map(
            fn (string $entry) => $dir . '/' . $entry,
            array_values(array_diff(scandir($dir) ?: [], ['.', '..'])),
        );

        usort($files, fn (string $a, string $b) => (filemtime($b) ?: 0) <=> (filemtime($a) ?: 0));

        return array_map(
            fn (string $file) => sprintf('  %-24s %6s  %s', basename($file), $this->size($file), date('H:i', filemtime($file) ?: 0)),
            $files,
        );
    }

    private function size(string $file): string
    {
        $bytes = (int) @filesize($file);

        return $bytes < 1024 ? "{$bytes}B" : round($bytes / 1024, 1) . 'K';
    }

}
