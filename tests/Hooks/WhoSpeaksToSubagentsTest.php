<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Hooks;

use JesseGall\CodeCommandments\Hooks\Handlers\BoardReminder;
use JesseGall\CodeCommandments\Hooks\Handlers\DispatchReminder;
use JesseGall\CodeCommandments\Hooks\Handlers\JournalRecorder;
use JesseGall\CodeCommandments\Hooks\Handlers\JudgeReminder;
use JesseGall\CodeCommandments\Hooks\Handlers\MergeGate;
use JesseGall\CodeCommandments\Hooks\Handlers\OrchestratorReminder;
use JesseGall\CodeCommandments\Hooks\Handlers\Remind;
use JesseGall\CodeCommandments\Hooks\Handlers\SharedBranchGate;
use JesseGall\CodeCommandments\Hooks\Handlers\SkillReminder;
use JesseGall\CodeCommandments\Hooks\Handlers\SourceReminder;
use JesseGall\CodeCommandments\Hooks\Handlers\WriteGate;
use JesseGall\CodeCommandments\Hooks\Discipline;
use JesseGall\CodeCommandments\Hooks\Hook;
use JesseGall\CodeCommandments\Hooks\HookRegistry;
use PHPUnit\Framework\TestCase;

/**
 * Which hooks a dispatched worker hears, and which it does not. The line is what the hook IS ABOUT, and
 * it is worth stating because both mistakes are silent: a discipline that stops at the orchestrator
 * leaves every worker writing code with none of the rules this package exists to keep in front of it,
 * and an orchestration hook that reaches a worker holds it at its stop demanding it run a build it is
 * not running.
 */
final class WhoSpeaksToSubagentsTest extends TestCase
{
    /**
     * The DISCIPLINES. A worker writes code, so it needs the cardinal rule, the skill for the subject it
     * is touching, the judge nudge before it commits, and the gate that makes it declare its work. It has
     * LESS context than the orchestrator, not more, so these are worth more there rather than less.
     *
     * @return list<array{class-string<Hook>}>
     */
    public static function disciplines(): array
    {
        return [
            [Remind::class],
            [SourceReminder::class],
            [SkillReminder::class],
            [JudgeReminder::class],
            [WriteGate::class],
            [MergeGate::class],
            [SharedBranchGate::class],
            [JournalRecorder::class],
        ];
    }

    /**
     * The ORCHESTRATION hooks. A worker is not running a build: it holds no board, dispatches nobody, and
     * has no scheduler of its own. Holding its stop to demand any of that would be a lockout inside a
     * dispatch, and the worker cannot even tell what is being asked of it.
     *
     * @return list<array{class-string<Hook>}>
     */
    public static function orchestration(): array
    {
        return [
            [DispatchReminder::class],
            [BoardReminder::class],
            [OrchestratorReminder::class],
        ];
    }

    /**
     * @dataProvider disciplines
     *
     * @param  class-string<Hook>  $hook
     */
    public function test_a_discipline_reaches_a_worker(string $hook): void
    {
        $this->assertTrue(
            $this->speaks($hook),
            "{$hook} is a discipline — a worker writing code needs it, and silencing it there means every dispatched agent works without it",
        );
    }

    /**
     * @dataProvider orchestration
     *
     * @param  class-string<Hook>  $hook
     */
    public function test_an_orchestration_hook_does_not(string $hook): void
    {
        $this->assertFalse(
            $this->speaks($hook),
            "{$hook} is about running a build, which a worker is not doing — reaching one would hold it at its stop for work it cannot do",
        );
    }

    /**
     * Asked of the MARKER, which is the whole point of it being one: a reader learns what a hook is for
     * from its class line rather than by opening it and finding a method.
     *
     * @param  class-string<Hook>  $hook
     */
    private function speaks(string $hook): bool
    {
        return is_subclass_of($hook, Discipline::class);
    }

    /**
     * The default is SILENCE. A hook written tomorrow reaches the orchestrator alone until somebody
     * decides, deliberately and visibly, that a worker needs it — which is the safe way round, since a
     * discipline nobody hears costs less than an orchestration hook holding a worker it cannot help.
     */
    public function test_a_hook_that_says_nothing_reaches_only_the_orchestrator(): void
    {
        $silent = 0;

        foreach (HookRegistry::BUILTINS as $hook) {
            $silent += is_subclass_of($hook, Discipline::class) ? 0 : 1;
        }

        $this->assertGreaterThan(count(self::disciplines()), $silent, 'most hooks are about running a build, and stop at the orchestrator');
    }
}
