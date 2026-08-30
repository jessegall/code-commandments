<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli\Orchestration;

use JesseGall\CodeCommandments\Cli\Console;
use JesseGall\CodeCommandments\Cli\Input;
use JesseGall\CodeCommandments\Cli\Orchestration\Board;
use JesseGall\CodeCommandments\Cli\Orchestration\Instance;
use JesseGall\CodeCommandments\Cli\Orchestration\BuildCommand;
use JesseGall\CodeCommandments\Cli\Orchestration\Stage;
use JesseGall\CodeCommandments\Tests\Cli\CapturingHookIO;
use JesseGall\CodeCommandments\Tests\Cli\FakeGit;
use JesseGall\CodeCommandments\Workspace;
use PHPUnit\Framework\TestCase;

/**
 * A build reaches an orchestration moment by writing a class into its PROFILE's `triggers/` — the same
 * folder its skills, sins and detectors already load from — and the moment it wants is the type its
 * `handle()` takes. What that buys it is the trap the profile records in words: never accept an item
 * whose receipt says COULD NOT MEASURE. Written down, that rule is followed when somebody remembers it;
 * as a handler it is refused, with a non-zero exit, in the process about to settle the work.
 */
final class ProfileTriggerTest extends TestCase
{
    private string $root;

    private string|false $priorProjectDir;

    private string|false $priorSession;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/cc-handler-' . uniqid('', true);
        mkdir($this->root . '/.commandments/orchestrator/profiles/demo', 0777, true);
        file_put_contents($this->root . '/.commandments/orchestrator/profiles/demo/profile.md', '# demo');
        $this->priorProjectDir = getenv('CLAUDE_PROJECT_DIR');
        $this->priorSession = getenv('CLAUDE_CODE_SESSION_ID');
        putenv('CLAUDE_PROJECT_DIR=' . $this->root);
        putenv('CLAUDE_CODE_SESSION_ID=handler-test');

        // AFTER the environment is set: `ofSession` reads it, so starting first writes the instance into
        // whichever session the suite itself is running under.
        Instance::inSession(Workspace::ofSession($this->root))->start('demo', '10:00');
    }

    protected function tearDown(): void
    {
        putenv($this->priorProjectDir === false ? 'CLAUDE_PROJECT_DIR' : 'CLAUDE_PROJECT_DIR=' . $this->priorProjectDir);
        putenv($this->priorSession === false ? 'CLAUDE_CODE_SESSION_ID' : 'CLAUDE_CODE_SESSION_ID=' . $this->priorSession);
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    public function test_a_projects_own_handler_stops_an_acceptance_it_refuses(): void
    {
        $this->handler('StopsAnUnmeasuredAcceptance', 'Accepting', 'refuse');
        $this->board()->claim('shipping', 'worker-a', '10:00');

        [$exit, $said] = $this->build('accept', 'shipping');

        $this->assertSame(Console::REFUSED, $exit, 'a refusal a script chains behind must exit non-zero');
        $this->assertStringContainsString('shipping was never measured', $said);
        $this->assertSame(Stage::Working, $this->board()->on('shipping')->unwrap()->stage, 'the board did not move');
    }

    public function test_the_same_refusal_on_a_report_is_demoted_to_a_note_and_the_report_stands(): void
    {
        // `Reported` is raised with the receipt already filed and the board already moved, so there is
        // nothing left to stop. The reason still has to reach somebody, so it is said rather than dropped.
        $this->handler('SpeaksAboutAnUnmeasuredReport', 'Reported', 'refuse');
        $this->board()->claim('shipping', 'worker-a', '10:00');

        [$exit, $said] = $this->build('report', 'shipping');

        $this->assertSame(0, $exit, 'nothing here can be refused');
        $this->assertStringContainsString('shipping was never measured', $said, 'the reason is not lost');
        $this->assertSame(Stage::Reported, $this->board()->on('shipping')->unwrap()->stage);
    }

    public function test_a_handler_that_passes_leaves_the_command_exactly_as_it_was(): void
    {
        $this->handler('LetsEverythingThrough', 'Accepting', 'pass');
        $this->board()->claim('shipping', 'worker-a', '10:00');

        [$exit, $said] = $this->build('accept', 'shipping');

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('shipping accepted', $said);
    }

    /**
     * Write a project handler for $moment that answers $verdict when nothing measured the item.
     */
    private function handler(string $name, string $moment, string $verdict): void
    {
        $answer = $verdict === 'refuse'
            ? 'Verdict::refuse("{$event->item()} was never measured — that is not a green.")'
            : 'Verdict::pass()';

        file_put_contents($this->triggersDir() . "/{$name}.php", <<<PHP
            <?php

            declare(strict_types=1);

            namespace Demo\\Commandments;

            use JesseGall\\CodeCommandments\\Cli\\Orchestration\\Events\\Trigger;
            use JesseGall\\CodeCommandments\\Cli\\Orchestration\\Events\\{$moment};
            use JesseGall\\CodeCommandments\\Cli\\Orchestration\\Events\\Verdict;

            final class {$name} extends Trigger
            {
                public function fire({$moment} \$event): Verdict
                {
                    return \$event->receipt->isNone() ? {$answer} : Verdict::pass();
                }
            }
            PHP);
    }

    /**
     * A trigger belongs to the PROFILE it serves, not to the project — it is a fact about this way of
     * working, and a session not orchestrating never loads one.
     */
    private function triggersDir(): string
    {
        $dir = $this->root . '/.commandments/orchestrator/profiles/demo/triggers';

        is_dir($dir) || mkdir($dir, 0777, true);

        return $dir;
    }

    private function board(): Board
    {
        return Board::inSession(Workspace::ofSession($this->root));
    }

    /**
     * Run `commandments build …` in the project, and answer both what it returned and what it said.
     *
     * @return array{int, string}
     */
    private function build(string ...$argv): array
    {
        $out = fopen('php://memory', 'r+');

        $exit = new BuildCommand(new CapturingHookIO(new FakeGit($this->root)), new Console($out))
            ->run(Input::fromArgv(['commandments', 'build', ...$argv]));

        rewind($out);

        return [$exit, (string) stream_get_contents($out)];
    }
}
