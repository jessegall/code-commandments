<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli;

use JesseGall\CodeCommandments\Hooks\Handlers\CompactionReminder;
use PHPUnit\Framework\TestCase;

/**
 * The compaction skill-reload nudge: a `SessionStart` hook that fires ONLY when the source is `compact`
 * — the one moment that silently drops loaded skills from context — and injects a reminder to reload
 * any skill governing the current task. Silent on every other session source, since none of them drops
 * a loaded skill. Driven through a {@see CapturingHookIO}, no harness.
 */
final class CompactionReminderTest extends TestCase
{
    public function test_a_compaction_injects_the_reload_reminder(): void
    {
        $context = $this->fire('compact');

        $this->assertStringContainsString('COMPACTED', $context);
        $this->assertStringContainsString('Skill tool', $context);
        $this->assertStringContainsString('reload', strtolower($context));
    }

    public function test_a_fresh_or_continuing_session_stays_silent(): void
    {
        // startup/clear begin a session with no skills loaded; resume/fork carry the loaded ones over —
        // only `compact` discards them, so only `compact` has anything to remind about.
        foreach (['startup', 'clear', 'resume', 'fork'] as $source) {
            $this->assertSame([], $this->emit($source), "{$source} must not fire the reload reminder");
        }
    }

    public function test_a_manual_run_with_no_payload_is_silent(): void
    {
        $io = new CapturingHookIO(new FakeGit('/tmp/project', 'sha', 'main'), []);
        new CompactionReminder($io)->run([]);

        $this->assertSame([], $io->emitted);
    }

    /** @return list<array<string, mixed>> */
    private function emit(string $source): array
    {
        $io = new CapturingHookIO(
            new FakeGit('/tmp/project', 'sha', 'main'),
            ['hook_event_name' => 'SessionStart', 'source' => $source],
        );
        new CompactionReminder($io)->run([]);

        return $io->emitted;
    }

    private function fire(string $source): string
    {
        $emitted = $this->emit($source);

        return $emitted === [] ? '' : $emitted[0]->context->unwrapOr('');
    }
}
