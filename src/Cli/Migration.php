<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli;

use JesseGall\PhpTypes\Option;

use JesseGall\CodeCommandments\Cli\Plan\PlanConstraints;
use JesseGall\CodeCommandments\Cli\Plan\PlanMarker;
use JesseGall\CodeCommandments\Cli\Plan\PlanTesting;
use JesseGall\CodeCommandments\Cli\State\LegacyLines;
use JesseGall\CodeCommandments\Cli\State\Legend;
use JesseGall\CodeCommandments\Cli\State\StateFile;
use JesseGall\CodeCommandments\Cli\State\State;
use JesseGall\CodeCommandments\Workspace;

/**
 * Carries a project's session state forward when the FORMAT of these files changes, on every `sync`.
 * What holds the USER's intent is CONVERTED — a plan's constraints are instructions the agent is
 * meant to be held to — and only the heartbeats, and the state of a feature that no longer exists,
 * are dropped. One-shot: {@see FORMAT} is stamped into the project once it has run.
 */
final class Migration
{
    /**
     * The state-file format this package writes. 1 was the positional-line marker; 2 is the named
     * `name: value` state with its legend; 3 keeps the judge checklists in {@see Workspace::SINS}
     * rather than at the session folder's top level; 4 has no stop gate.
     */
    public const int FORMAT = 4;

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
            $this->constraints($dir),
            $this->testing($dir),
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
     * The plan marker and its separate stuck signal.
     */
    private function plan(string $dir): ?string
    {
        $found = $this->legacy("{$dir}/.plan-active");

        if ($found->isNone()) {
            return null;
        }

        $marker = $found->unwrap();
        $stuck = $this->legacy("{$dir}/.plan-stuck");
        $signal = $stuck->unwrapOr(new LegacyLines()); // no file at all reads as the empty signal

        new StateFile("{$dir}/.plan-active", PlanMarker::legend())->write(new State(
            head: $marker->text(0),
            no_progress_nudges: $marker->int(1),
            total_nudges: $marker->int(2),
            stuck: $stuck->isSome(),
            stuck_at: $signal->text(0),
        ));

        $this->delete($dir, ['.plan-stuck']);

        return 'the active plan';
    }

    /**
     * The plan's local constraints and the HEAD they were verified at — a list and a stamp, in two
     * files, now one.
     */
    private function constraints(string $dir): ?string
    {
        $rules = $this->lines("{$dir}/.plan-constraints");

        if ($rules === null || $this->isCurrent($rules)) {
            return null;
        }

        $verified = new LegacyLines($this->lines("{$dir}/.constraints-verified") ?? []);

        new StateFile("{$dir}/.plan-constraints", PlanConstraints::legend())
            ->write(new State(verified_at: $verified->text(0))->withItems($rules));

        $this->delete($dir, ['.constraints-verified']);

        return count($rules) . ' plan constraint(s)';
    }

    /**
     * The testing methodology — a bare line of prose in a file of its own.
     */
    private function testing(string $dir): ?string
    {
        $lines = $this->lines("{$dir}/.plan-testing");

        if ($lines === null || $lines === [] || $this->isCurrent($lines)) {
            return null;
        }

        new StateFile("{$dir}/.plan-testing", PlanTesting::legend())
            ->write(new State(methodology: implode(' ', $lines)));

        return 'the testing methodology';
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
     * The value lines of an OLD marker — everything above the `-----` — or null when the file isn't
     * there or has already been converted. The old shape is positional (`0`, `0`, `5`) with one
     * concern per file: a plan in `.plan-active` with its stuck signal in `.plan-stuck`, constraints
     * split across `.plan-constraints`/`.constraints-verified`.
     *
     * @return Option<LegacyLines>
     */
    private function legacy(string $path): Option
    {
        $lines = $this->lines($path);

        return $lines === null || $this->isCurrent($lines) ? Option::none() : Option::some(new LegacyLines($lines));
    }

    /**
     * @return list<string>|null  the non-blank lines above the separator; null when there is no file
     */
    private function lines(string $path): ?array
    {
        if (! is_file($path)) {
            return null;
        }

        $lines = [];

        foreach (preg_split('/\R/', (string) file_get_contents($path)) ?: [] as $line) {
            if (trim($line) === '-----') {
                break;
            }

            if (trim($line) !== '') {
                $lines[] = $line;
            }
        }

        return $lines;
    }

    /**
     * Is this file already in the current format — its first value line a `name: value` assignment?
     * A legacy marker's first line is a bare number, a HEAD or a constraint, never that.
     *
     * @param  list<string>  $lines
     */
    private function isCurrent(array $lines): bool
    {
        return isset($lines[0]) && str_contains($lines[0], ': ');
    }

    /**
     * @param  list<string>  $files
     */
    private function delete(string $dir, array $files): void
    {
        foreach ($files as $file) {
            @unlink("{$dir}/{$file}");
        }
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
