<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Plan;

use JesseGall\CodeCommandments\Cli\State\Legend;
use JesseGall\CodeCommandments\Cli\State\State;
use JesseGall\CodeCommandments\Cli\State\StateFile;
use JesseGall\CodeCommandments\PlanProfile;
use JesseGall\CodeCommandments\Workspace;

/**
 * The testing-methodology state for the active plan — how tests are written and run as the agent
 * grinds it. Unlike {@see PlanConstraints} this is a working style, not a diff-verified invariant, so
 * there is no verification stamp and no `plan done` gate: it is chosen once at approval, recorded for
 * the run, and re-surfaced by {@see \JesseGall\CodeCommandments\Hooks\Handlers\TestingReminder} so it
 * survives a long grind (or a compaction). {@see effective} is this run's choice if the user made one,
 * else the project default from {@see PlanProfile::testFlow}. Session-scoped like {@see PlanMarker}.
 */
final class PlanTesting
{
    public function __construct(
        private readonly StateFile $file,
        private readonly PlanProfile $plan,
    ) {}

    public static function inSession(Workspace $workspace, PlanProfile $plan): self
    {
        return new self(new StateFile($workspace->path('.plan-testing'), self::legend()), $plan);
    }

    public static function legend(): Legend
    {
        return new Legend(
            'How tests are written and run while THIS plan is ground out (`commandments plan testing '
                . '"<methodology>"`), re-surfaced by the testing reminder so it survives a long run.',
            ['methodology' => "this run's chosen methodology. Empty means the project default from "
                . '`$config->planExecution(...)->testFlow(...)` is in force'],
            defaults: new State(methodology: ''),
            safe: "deleting it falls back to the project's default methodology",
        );
    }

    /**
     * The methodology chosen for THIS run, or '' when the user hasn't set one.
     */
    public function chosen(): string
    {
        return $this->file->read()->text('methodology');
    }

    /**
     * The methodology in force: this run's explicit choice, or the project default when none was set.
     */
    public function effective(): string
    {
        return $this->chosen() ?: $this->plan->testFlow();
    }

    /**
     * Record the methodology for this run — a blank value clears the choice (falling back to the default).
     */
    public function set(string $methodology): void
    {
        if (trim($methodology) === '') {
            $this->clear();

            return;
        }

        $this->file->write(new State(methodology: $methodology));
    }

    /**
     * Drop this run's choice — the plan is finished (the default from config remains).
     */
    public function clear(): void
    {
        $this->file->delete();
    }
}
