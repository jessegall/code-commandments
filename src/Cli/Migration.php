<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli;

use JesseGall\CodeCommandments\Cli\State\Legend;
use JesseGall\CodeCommandments\Cli\State\StateFile;
use JesseGall\CodeCommandments\Cli\State\State;
use JesseGall\CodeCommandments\Workspace;

/**
 * Carries a project's session state forward when the FORMAT of these files changes, on every `sync`.
 * What still has a reader is MOVED (the judge checklists); the heartbeats, and the state of a feature
 * that no longer exists, are dropped. One-shot: {@see FORMAT} is stamped into the project once it has run.
 */
final class Migration
{
    /**
     * The state-file format this package writes. 1 was the positional-line marker; 2 is the named
     * `name: value` state with its legend; 3 keeps the judge checklists in {@see Workspace::SINS}
     * rather than at the session folder's top level; 4 has no stop gate; 5 has no plan.
     */
    public const int FORMAT = 5;

    public function __construct(private readonly Workspace $workspace) {}

    /**
     * Bring every session folder up to {@see FORMAT}, and stamp the project so it happens once.
     *
     * @return list<string>  what it converted, for a caller that wants to report it; [] when there
     *                       was nothing to do
     */
    public function run(): array
    {
        $stamp = $this->stamp();

        if ($stamp->read()->int('format') >= self::FORMAT) {
            return [];
        }

        $done = [];

        foreach ($this->sessions() as $dir) {
            $done = [...$done, ...$this->session($dir)];
        }

        $stamp->write(new State(format: self::FORMAT));

        return $done;
    }

    /**
     * @return list<string>
     */
    private function session(string $dir): array
    {
        return array_values(array_filter([
            $this->gate($dir),
            $this->plan($dir),
            $this->checklists($dir),
            $this->counters($dir),
        ]));
    }

    /**
     * The stop gate. The feature is gone — the journal's to-do list is where deferred work lives now —
     * so whatever a session still holds of it (the live marker, a paused twin, a pending claim, its
     * counters) is deleted rather than carried into a shape nothing reads.
     */
    private function gate(string $dir): ?string
    {
        $files = glob("{$dir}/.until*") ?: [];

        foreach ($files as $path) {
            @unlink($path);
        }

        return $files === [] ? null : count($files) . ' stop-gate file(s) removed';
    }

    /**
     * The plan: its marker and stuck signal, constraints, testing choice and working-state record. The
     * feature is gone — plan execution lives in its own package now — so whatever a session still holds
     * of it is deleted rather than carried into a shape nothing reads.
     */
    private function plan(string $dir): ?string
    {
        $files = array_merge(...array_map(static fn (string $glob): array => glob("{$dir}/{$glob}") ?: [], [
            '.plan-*', '.constraints-verified',
        ]));

        foreach ($files as $path) {
            @unlink($path);
        }

        return $files === [] ? null : count($files) . ' plan file(s) removed';
    }

    /**
     * The judge checklists a project holds at its session folders' top level are MOVED into
     * {@see Workspace::SINS}, not dropped: each one is the record of what was true when its run
     * happened, and `--repent=<stamp>` still addresses it. A name already taken in the new folder
     * wins — the file there was written by a judge run that has already moved on.
     */
    private function checklists(string $dir): ?string
    {
        $folder = $dir . '/' . Workspace::SINS;
        $strays = [...glob("{$dir}/sins.md") ?: [], ...glob("{$dir}/sins-*.md") ?: []];

        if ($strays === []) {
            return null;
        }

        if (! is_dir($folder) && ! @mkdir($folder, 0755, true) && ! is_dir($folder)) {
            return null; // Nowhere to put them — leave them where they are rather than lose them.
        }

        $moved = 0;

        foreach ($strays as $stray) {
            $target = $folder . '/' . basename($stray);

            if (! is_file($target) && @rename($stray, $target)) {
                $moved++;
            }
        }

        return $moved === 0 ? null : "{$moved} judge checklist(s) moved into " . Workspace::SINS . '/';
    }

    /**
     * The hook heartbeats. These are DROPPED rather than converted: a counter holds nothing of the
     * user's, and the worst a fresh one costs is a nudge arriving a few tool uses later than it would
     * have. They regenerate on the next tool use.
     */
    private function counters(string $dir): ?string
    {
        $counters = glob("{$dir}/.*-count") ?: [];

        foreach ($counters as $path) {
            @unlink($path);
        }

        return $counters === [] ? null : count($counters) . ' hook counter(s) reset';
    }

    /**
     * @return list<string>
     */
    private function sessions(): array
    {
        return glob($this->workspace->dir() . '/sessions/*', GLOB_ONLYDIR) ?: [];
    }

    /**
     * The project's record of which format its state files are in — durable, not session-scoped, since
     * it answers for all of them.
     */
    private function stamp(): StateFile
    {
        return new StateFile($this->workspace->shared('.state-format'), new Legend(
            'Which format code-commandments writes its session state files in. It exists so an upgrade '
                . 'can convert what is already on disk exactly once.',
            ['format' => 'the state-file format this project has been brought up to'],
            defaults: new State(format: 0),
            safe: 'the conversion simply runs again on the next `composer update`',
        ));
    }
}
