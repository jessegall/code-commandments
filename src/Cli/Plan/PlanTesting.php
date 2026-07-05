<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Plan;

use JesseGall\CodeCommandments\PlanProfile;

/**
 * The testing-methodology state for the active plan — how tests are written and run as the agent
 * grinds it. Unlike {@see PlanConstraints} this is a working style, not a diff-verified invariant, so
 * there is no verification stamp and no `plan done` gate: it is chosen once at approval, recorded for
 * the run, and re-surfaced by {@see \JesseGall\CodeCommandments\Hooks\Handlers\TestingReminder} so it
 * survives a long grind (or a compaction). {@see effective} is this run's choice if the user made one,
 * else the project default from {@see PlanProfile::testFlow}. Worktree-scoped like {@see PlanMarker}.
 */
final class PlanTesting
{
    public function __construct(
        private readonly string $path,
        private readonly PlanProfile $plan,
    ) {}

    public static function inWorktree(string $root, PlanProfile $plan): self
    {
        return new self($root . '/.commandments/.plan-testing', $plan);
    }

    /**
     * The methodology chosen for THIS run, or '' when the user hasn't set one.
     */
    public function chosen(): string
    {
        if (! is_file($this->path)) {
            return '';
        }

        return trim((string) file_get_contents($this->path));
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
        $methodology = trim($methodology);

        if ($methodology === '') {
            $this->clear();

            return;
        }

        @mkdir(dirname($this->path), 0777, true);
        @file_put_contents($this->path, $methodology . "\n");
    }

    /**
     * Drop this run's choice — the plan is finished (the default from config remains).
     */
    public function clear(): void
    {
        @unlink($this->path);
    }
}
