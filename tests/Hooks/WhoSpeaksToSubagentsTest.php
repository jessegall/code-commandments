<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Hooks;

use JesseGall\CodeCommandments\Hooks\Handlers\JudgeReminder;
use JesseGall\CodeCommandments\Hooks\Handlers\SharedBranchGate;
use JesseGall\CodeCommandments\Hooks\Handlers\SkillReminder;
use JesseGall\CodeCommandments\Hooks\Handlers\SourceReminder;
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
     * The DISCIPLINES. A worker writes code, so it needs the skill for the subject it is touching, the
     * judge nudge before it commits, and the gate that makes it declare its work. It has LESS context
     * than its parent, not more, so these are worth more there rather than less.
     *
     * @return list<array{class-string<Hook>}>
     */
    public static function disciplines(): array
    {
        return [
            [SourceReminder::class],
            [SkillReminder::class],
            [JudgeReminder::class],
            [SharedBranchGate::class],
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

        $this->assertGreaterThan(0, $silent, 'a hook says nothing to a worker until it declares otherwise');
    }
}
