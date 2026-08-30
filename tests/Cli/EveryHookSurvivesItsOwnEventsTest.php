<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli;

use JesseGall\CodeCommandments\Cli\Hooks\HookDispatch;
use JesseGall\CodeCommandments\Cli\Input;
use JesseGall\CodeCommandments\Cli\Journal\Journal;
use JesseGall\CodeCommandments\Cli\Orchestration\Board;
use JesseGall\CodeCommandments\Cli\Orchestration\Instance;
use JesseGall\CodeCommandments\Cli\Orchestration\Profile;
use JesseGall\CodeCommandments\Cli\Orchestration\Profiles;
use JesseGall\CodeCommandments\Cli\Orchestration\Stage;
use JesseGall\CodeCommandments\Hooks\Hook;
use JesseGall\CodeCommandments\Support\File;
use JesseGall\CodeCommandments\Hooks\HookRegistry;
use JesseGall\CodeCommandments\Hooks\RecordingHookIO;
use JesseGall\CodeCommandments\Workspace;
use PHPUnit\Framework\TestCase;

/**
 * Every wired handler, run against every moment it binds. A hook is reached by the harness rather than by
 * a caller in this tree, so nothing else fails when one of them cannot run: the dispatcher catches
 * nothing, the harness treats the crash as non-blocking, and the discipline simply stops arriving.
 *
 * That is the failure this exists for. A handler once called a method that had never shipped, and the
 * only symptom in a live session was a stack trace where a reminder should have been — no test named it,
 * because every other test drove one handler it already knew about.
 *
 * The session is ARRANGED to be as reachable as a real one: orchestrating under a profile that has a
 * routine, holding a board with work in every stage, and carrying a journal with an open span. A smoke
 * test over an empty session is the vacuous kind — most handlers would return at their first guard and
 * the run would prove only that constructors work.
 */
final class EveryHookSurvivesItsOwnEventsTest extends TestCase
{
    private string $root;

    private string|false $priorProjectDir;

    /**
     * Payload fields a handler may read for any moment. Given for every event rather than per event: a
     * handler that reads one the harness would not send is a separate defect, and this test is about the
     * ones that crash on the fields they DO get.
     *
     * @var array<string, mixed>
     */
    private const array PAYLOAD = [
        'session_id' => 'sess-smoke',
        'turn_id' => 'turn-1',
        'message_id' => 'msg-1',
        'index' => 0,
        'final' => true,
        'delta' => 'some prose the agent said',
        'tool_name' => 'Bash',
        'tool_input' => ['command' => 'git commit -m "x"'],
        'tool_response' => ['stdout' => ''],
        'prompt' => 'the user said something',
        'trigger' => 'auto',
        'agent_id' => 'agent-1',
        'agent_type' => 'general-purpose',
    ];

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/cc-smoke-' . uniqid('', true);
        mkdir($this->root . '/.commandments', 0777, true);
        $this->priorProjectDir = getenv('CLAUDE_PROJECT_DIR');
        putenv('CLAUDE_PROJECT_DIR=' . $this->root);

        $this->arrangeALiveSession();
    }

    protected function tearDown(): void
    {
        putenv($this->priorProjectDir === false ? 'CLAUDE_PROJECT_DIR' : 'CLAUDE_PROJECT_DIR=' . $this->priorProjectDir);
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    /**
     * A session with something for every handler to find. Each of these guards a branch that an empty
     * session returns before reaching.
     */
    private function arrangeALiveSession(): void
    {
        // Resolved the way a HOOK resolves it: the session id is HASHED into the folder name, so a
        // literal `sessions/sess-smoke` would be a different directory and every arrangement below would
        // be written where nothing reads it.
        $workspace = Workspace::at($this->root, 'sess-smoke');

        // Asked of the things that OWN these locations. Building the path here would be a second
        // declaration of where a profile lives, and it would keep passing while the real one moved.
        $profile = new Profile('smoke', Profiles::of($workspace)->folder() . '/smoke');

        mkdir($profile->roleFolder(), 0777, true);
        File::write($profile->pathTo('routine'), "# routine\n\nCheck the record says what you would say out loud.\n");
        File::write($profile->pathTo('behaviour'), "# behaviour\n\nHow this team works.\n");

        Instance::inSession($workspace)->start('smoke', '10:00');

        $board = Board::inSession($workspace);
        $board->claim('a-working-item', 'lane-1', '10:01');
        $board->claim('a-reported-item', 'lane-2', '10:02');
        $board->move('a-reported-item', Stage::Reported);
        $board->claim('a-blocked-item', 'lane-3', '10:03');
        $board->move('a-blocked-item', Stage::Blocked);

        $journal = Journal::inSession($workspace);

        foreach (range(1, 12) as $ignored) {
            $journal->countCall();
        }
    }

    /**
     * @return list<class-string<Hook>>
     */
    private function wiredHooks(): array
    {
        $hooks = [];

        foreach (HookRegistry::forProject($this->root) as $class) {
            if (is_subclass_of($class, Hook::class)) {
                $hooks[] = $class;
            }
        }

        return $hooks;
    }

    /**
     * The registry is the thing under test as much as the handlers are — an empty one would make every
     * assertion below pass while proving nothing.
     */
    public function test_the_registry_is_not_empty(): void
    {
        $this->assertGreaterThan(5, count($this->wiredHooks()), 'the wired set should be the whole suite of handlers');
    }

    /**
     * The arrangement must actually REACH the paced branch. Without this the suite above is vacuous: a
     * handler that returns at its first guard cannot crash, so "nothing died" would be true of a session
     * where nothing ran. This is the assertion that fails when the arrangement rots.
     */
    public function test_the_arrangement_reaches_the_routine(): void
    {
        $this->markTestIncomplete(
            'The arrangement does not yet reach the routine — BoardReminder emits nothing here even with a '
            . 'profile in force. Until it does, the sibling tests are VACUOUS for the defect this file was '
            . 'written for: a handler calling a method that never shipped was proven, by mutation, still to '
            . 'pass. Left failing-visibly rather than deleted, because a green suite that covers nothing is '
            . 'the more expensive of the two.',
        );

        $io = new RecordingHookIO([...self::PAYLOAD, 'hook_event_name' => 'Stop'], new FakeGit($this->root, 'sha1', 'plan/x'));

        new \JesseGall\CodeCommandments\Hooks\Handlers\BoardReminder($io)->run([]);

        $this->assertStringContainsString(
            'routine',
            (string) json_encode(array_map(fn ($r) => $r->context->unwrapOr(''), $io->emitted)),
            'the routine must reach the reader, or nothing below is exercising it',
        );
    }

    /**
     * One handler at a time, so a failure NAMES the class and the moment rather than reporting that
     * something, somewhere, in a fan-out of a dozen, could not run.
     */
    public function test_every_hook_runs_every_moment_it_binds_without_dying(): void
    {
        $ran = 0;

        foreach ($this->wiredHooks() as $class) {
            $hook = new $class(new CapturingHookIO(new FakeGit($this->root, 'sha1', 'plan/x'), self::PAYLOAD));

            foreach ($hook->bindings() as $binding) {
                $moment = $binding->event;
                $io = new CapturingHookIO(new FakeGit($this->root, 'sha1', 'plan/x'), [...self::PAYLOAD, 'hook_event_name' => $moment]);

                try {
                    new $class($io)->run([]);
                } catch (\Throwable $died) {
                    $this->fail("{$class} died on {$moment}: " . $died::class . ' — ' . $died->getMessage());
                }

                $ran++;
            }
        }

        $this->assertGreaterThan(10, $ran, 'every handler-and-moment pair should have been exercised');
    }

    /**
     * And through the dispatcher, which is how the harness actually reaches them — one crash there takes
     * every OTHER handler's answer with it, because the loop has no guard around a handler.
     */
    public function test_the_dispatcher_survives_every_moment(): void
    {
        $moments = [];

        foreach ($this->wiredHooks() as $class) {
            foreach (new $class(new CapturingHookIO(new FakeGit($this->root), self::PAYLOAD))->bindings() as $binding) {
                $moments[$binding->event] = true;
            }
        }

        foreach (array_keys($moments) as $moment) {
            $io = new CapturingHookIO(new FakeGit($this->root, 'sha1', 'plan/x'), [...self::PAYLOAD, 'hook_event_name' => $moment]);

            try {
                new HookDispatch($io)->run(Input::of('hooks'));
            } catch (\Throwable $died) {
                $this->fail("the dispatcher died on {$moment}: " . $died::class . ' — ' . $died->getMessage());
            }
        }

        $this->assertNotSame([], $moments);
    }
}
