<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\State;

use JesseGall\CodeCommandments\Cli\Plan\PlanConstraints;
use JesseGall\CodeCommandments\Cli\Plan\PlanMarker;
use JesseGall\CodeCommandments\Cli\Plan\PlanTesting;
use JesseGall\CodeCommandments\Cli\Until\Condition;
use JesseGall\CodeCommandments\Cli\Until\UntilGate;
use JesseGall\CodeCommandments\Workspace;

/**
 * Carries a project's session state forward when the FORMAT of these files changes — run on every
 * `sync`, which is what `composer update` triggers, so a consumer upgrades without noticing.
 *
 * It exists for one reason: some of this state is the USER's. A counter is a heartbeat and losing it
 * costs nothing, but the conditions of a stop gate are what the user asked for, and a plan's local
 * constraints are what they told the agent to hold to. Deleting those on an upgrade would quietly
 * throw away instructions the agent is meant to be held to — so what carries intent is CONVERTED, and
 * only the pure heartbeats are dropped.
 *
 * The old shape it reads: positional value lines (`0`, `0`, `5`) above a `-----`, one concern per
 * file — a gate in `.until`, its paused twin in `.until.pause`, its claim in `.until.claim`, its
 * counts in `.until-work-count`/`.until-todo-drift-count`, a plan in `.plan-active` with its stuck
 * signal in `.plan-stuck`, constraints split across `.plan-constraints`/`.constraints-verified`. All
 * of it becomes one {@see StateFile} per feature, named and self-describing.
 *
 * ONE-SHOT, and it says so: {@see FORMAT} is stamped into the project when it has run, and a project
 * already at that number is skipped. When every consumer has upgraded, this class can be deleted
 * whole — nothing else depends on it.
 */
final class Migration
{
    /**
     * The state-file format this package writes. 1 was the positional-line marker; 2 is the named
     * `name: value` state with its legend.
     */
    public const int FORMAT = 2;

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
            $this->counters($dir),
        ]));
    }

    /**
     * The stop gate: the live conditions, the ones a pause set aside, the pending claim and the two
     * counts were five files; they are one now. A gate that was PAUSED becomes a `paused: yes` flag
     * over the same conditions. If a session somehow holds both a live and a paused marker (only
     * possible from before #418), the live one wins and the set-aside conditions are folded in behind
     * it — keeping the user's conditions matters more than keeping a distinction that shape should
     * never have had.
     */
    private function gate(string $dir): ?string
    {
        $live = $this->legacy("{$dir}/.until");
        $paused = $this->legacy("{$dir}/.until.pause");

        if ($live === null && $paused === null) {
            return null;
        }

        $source = $live ?? $paused;
        $conditions = $this->conditions($source);
        $lastId = max($this->headerOf($source) === 3 ? (int) $source[2] : 0, ...[0, ...array_map(
            fn (Condition $c): int => $c->id,
            $conditions,
        )]);

        if ($live !== null && $paused !== null) {
            foreach ($this->conditions($paused) as $condition) {
                $conditions[] = new Condition(++$lastId, $condition->text);
            }
        }

        if ($conditions === []) {
            return $this->drop($dir, ['.until', '.until.pause', '.until.claim']);
        }

        $claim = $this->legacy("{$dir}/.until.claim") ?? [];

        new StateFile("{$dir}/.until", UntilGate::legend())->write(new State(
            held_stops: (int) ($source[0] ?? 0),
            todo_drift: $this->count("{$dir}/.until-todo-drift-count"),
            last_id: $lastId,
            paused: $live === null,
            stuck: ($source[1] ?? '0') === '1',
            claim_round: (int) ($claim[0] ?? 0),
        )->withItems(array_map(fn (Condition $c): string => $c->line(), $conditions)));

        $this->delete($dir, ['.until.pause', '.until.claim', '.until-work-count', '.until-todo-drift-count']);

        return count($conditions) . ' stop condition(s)';
    }

    /**
     * The plan marker and its separate stuck signal.
     */
    private function plan(string $dir): ?string
    {
        $marker = $this->legacy("{$dir}/.plan-active");

        if ($marker === null) {
            return null;
        }

        $stuck = $this->legacy("{$dir}/.plan-stuck");

        new StateFile("{$dir}/.plan-active", PlanMarker::legend())->write(new State(
            head: $marker[0] ?? '',
            no_progress_nudges: (int) ($marker[1] ?? 0),
            total_nudges: (int) ($marker[2] ?? 0),
            stuck: $stuck !== null,
            stuck_at: $stuck[0] ?? '',
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

        $verified = $this->lines("{$dir}/.constraints-verified") ?? [];

        new StateFile("{$dir}/.plan-constraints", PlanConstraints::legend())
            ->write(new State(verified_at: $verified[0] ?? '')->withItems($rules));

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
     * there or has already been converted.
     *
     * @return list<string>|null
     */
    private function legacy(string $path): ?array
    {
        $lines = $this->lines($path);

        return $lines === null || $this->isCurrent($lines) ? null : $lines;
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
     * The conditions of an old gate marker: `id<TAB>text` lines, or — from before stable ids — bare
     * condition texts, which take their position as their id.
     *
     * @param  list<string>  $marker
     * @return list<Condition>
     */
    private function conditions(array $marker): array
    {
        $conditions = [];

        foreach (array_slice($marker, $this->headerOf($marker)) as $line) {
            $condition = Condition::read($line);
            $conditions[] = $condition ?? new Condition(count($conditions) + 1, $line);
        }

        return $conditions;
    }

    /**
     * How many header lines an old gate marker carries before its conditions: three once ids were
     * stable (blocks, stuck, last-id), two before that — the third line was a condition itself.
     *
     * @param  list<string>  $marker
     */
    private function headerOf(array $marker): int
    {
        return ctype_digit($marker[2] ?? '') ? 3 : 2;
    }

    private function count(string $path): int
    {
        return (int) (($this->lines($path) ?? [])[0] ?? 0);
    }

    /**
     * @param  list<string>  $files
     */
    private function drop(string $dir, array $files): ?string
    {
        $this->delete($dir, $files);

        return null;
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
