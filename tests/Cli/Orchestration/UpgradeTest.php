<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli\Orchestration;

use JesseGall\CodeCommandments\Cli\Console;
use JesseGall\CodeCommandments\Cli\Input;
use JesseGall\CodeCommandments\Cli\Orchestration\Board;
use JesseGall\CodeCommandments\Cli\Orchestration\Checkout;
use JesseGall\CodeCommandments\Cli\Orchestration\Instance;
use JesseGall\CodeCommandments\Cli\Orchestration\Release;
use JesseGall\CodeCommandments\Cli\Orchestration\Stage;
use JesseGall\CodeCommandments\Cli\Orchestration\UpgradeCommand;
use JesseGall\CodeCommandments\Workspace;
use PHPUnit\Framework\TestCase;

/**
 * Moving the package, and bringing every lane with it. Every assertion here is one of the failures that
 * paid for the command: a lane left on its old vendor answering questions about the new rules, a version
 * quoted that nobody measured, and a `vendor/` swapped under a worker who was still using it.
 *
 * The lanes are real worktrees with real lockfiles, and the `lane.sh` is a real script that really copies
 * the project's vendor — so what is asserted is the version a checkout ACTUALLY runs afterwards, not that
 * a step was called. Only composer is faked, because what it would say over the network is the one fact a
 * test has no business measuring.
 */
final class UpgradeTest extends TestCase
{
    private const string BEFORE = 'v1.0.0';

    private const string AFTER = 'v2.0.0';

    private string $root;

    private string $cwd;

    private string|false $priorProjectDir;

    private string|false $priorSession;

    protected function setUp(): void
    {
        $this->cwd = getcwd() ?: '.';
        $this->priorProjectDir = getenv('CLAUDE_PROJECT_DIR');
        $this->priorSession = getenv('CLAUDE_CODE_SESSION_ID');

        $root = sys_get_temp_dir() . '/cc-upgrade-' . uniqid('', true);
        mkdir($root, 0777, true);
        $this->root = realpath($root) ?: $root;

        $this->repository();

        putenv('CLAUDE_PROJECT_DIR=' . $this->root);
        putenv('CLAUDE_CODE_SESSION_ID=upgrade-test');

        Lockfile::write($this->root, self::BEFORE);
        Lockfile::write($this->lane('alpha'), 'v0.9.0');
        Lockfile::write($this->lane('beta'), 'v0.9.0');

        $this->profile();
    }

    protected function tearDown(): void
    {
        chdir($this->cwd);
        putenv($this->priorProjectDir === false ? 'CLAUDE_PROJECT_DIR' : 'CLAUDE_PROJECT_DIR=' . $this->priorProjectDir);
        putenv($this->priorSession === false ? 'CLAUDE_CODE_SESSION_ID' : 'CLAUDE_CODE_SESSION_ID=' . $this->priorSession);
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    /**
     * The failure the whole command exists for: a root update that leaves a lane on the vendor it was
     * seeded with, so it goes on judging by last week's rules and answering as though they were this
     * week's.
     */
    public function test_every_lane_comes_forward_with_the_project(): void
    {
        $composer = new FakeComposer(Release::measured(self::AFTER), installs: self::AFTER);

        $this->assertSame(0, $this->upgrade($composer));

        $this->assertSame(self::AFTER, $this->versionIn($this->root));
        $this->assertSame(self::AFTER, $this->versionIn($this->lane('alpha')), 'the lane was left behind');
        $this->assertSame(self::AFTER, $this->versionIn($this->lane('beta')), 'the lane was left behind');
    }

    /**
     * The one place it does less than asked. Swapping a vendor under a running builder makes its next
     * gate failure indistinguishable from a real bug in its work, so the lane is left exactly as it was —
     * and NAMED, because a lane silently skipped is the same stale binary with better manners.
     */
    public function test_a_lane_a_worker_is_working_in_is_left_alone_and_named(): void
    {
        $this->board()->claim('alpha', 'builder-a', '10:00');

        $composer = new FakeComposer(Release::measured(self::AFTER), installs: self::AFTER);
        $printed = '';

        $this->assertSame(Console::REFUSED, $this->upgrade($composer, $printed), 'a lane left behind is not a green');

        $this->assertSame('v0.9.0', $this->versionIn($this->lane('alpha')), 'a live worker\'s vendor was swapped');
        $this->assertSame(self::AFTER, $this->versionIn($this->lane('beta')));

        $this->assertStringContainsString('held by builder-a (alpha) — SKIPPED', $printed);
        $this->assertStringContainsString('once their workers report', $printed);
    }

    /**
     * A hold is a fact about the board, not about a process — but only WORKING occupies the lane. Once a
     * worker has reported it is waiting on the orchestrator and is running nothing a new vendor could
     * spoil, which is what makes "run it again when they report" a real instruction rather than a hope.
     */
    public function test_a_lane_whose_worker_has_reported_is_brought_forward(): void
    {
        $this->board()->claim('alpha', 'builder-a', '10:00');
        $this->board()->move('alpha', Stage::Reported);

        $this->assertSame(0, $this->upgrade(new FakeComposer(Release::measured(self::AFTER), installs: self::AFTER)));
        $this->assertSame(self::AFTER, $this->versionIn($this->lane('alpha')));
    }

    /**
     * `--check` earns its place by naming BOTH numbers — "described as shipped" and "installable" are
     * different sentences, and they came apart twice on a real build. And it changes nothing: no update,
     * no sync, no lane touched.
     */
    public function test_check_names_both_numbers_and_moves_nothing(): void
    {
        $composer = new FakeComposer(Release::measured(self::AFTER));
        $printed = '';

        $this->assertSame(0, $this->upgrade($composer, $printed, '--check'));

        $this->assertSame(['latest'], $composer->ran, '--check must not update or sync');
        $this->assertSame(self::BEFORE, $this->versionIn($this->root));
        $this->assertSame('v0.9.0', $this->versionIn($this->lane('alpha')), '--check touched a lane');

        $this->assertStringContainsString(self::BEFORE, $printed);
        $this->assertStringContainsString(self::AFTER, $printed);
        $this->assertStringContainsString('behind', $printed);
    }

    /**
     * The warning this command was written under: a tool must never state a fact it did not measure. When
     * composer cannot say what is installable, the answer is that nobody found out — said in those words,
     * and exited non-zero, because a script chaining behind this can only read the code and "not knowing"
     * must not arrive as "up to date".
     */
    public function test_an_availability_nobody_could_read_is_never_reported_as_current(): void
    {
        $printed = '';
        $composer = new FakeComposer(Release::unmeasurable('composer is not on the PATH.'));

        $this->assertSame(Console::REFUSED, $this->upgrade($composer, $printed, '--check'));

        $this->assertStringContainsString(Release::COULD_NOT_MEASURE, $printed);
        $this->assertStringContainsString('composer is not on the PATH.', $printed);
        $this->assertStringNotContainsString('runs what it can install', $printed);
    }

    /**
     * Each step only when the one before it worked. A sync onto a failed update republishes the old
     * curriculum and reports success, and re-preparing a lane from a vendor that did not move copies the
     * old version over the old version.
     */
    public function test_a_failed_update_stops_everything_after_it(): void
    {
        $composer = new FakeComposer(Release::measured(self::AFTER), updates: 1);

        $this->assertSame(Console::REFUSED, $this->upgrade($composer));

        $this->assertSame(['update'], $composer->ran, 'something ran after a failed update');
        $this->assertSame('v0.9.0', $this->versionIn($this->lane('alpha')));
    }

    /**
     * A failed sync leaves the skills stale against a new binary — the same drift as a lane, one directory
     * up — so the lanes are not touched either and the run says which half is wrong.
     */
    public function test_a_failed_sync_leaves_the_lanes_alone(): void
    {
        $composer = new FakeComposer(Release::measured(self::AFTER), syncs: 1, installs: self::AFTER);

        $this->assertSame(Console::REFUSED, $this->upgrade($composer));
        $this->assertSame('v0.9.0', $this->versionIn($this->lane('alpha')));
    }

    /**
     * Nobody has ever wanted the package moved without it: forgetting `sync` leaves the skills stale
     * while the binary is new, so it is not a flag you have to remember.
     */
    public function test_sync_is_implied(): void
    {
        $composer = new FakeComposer(Release::measured(self::AFTER), installs: self::AFTER);

        $this->upgrade($composer);

        $this->assertContains('sync', $composer->ran);
    }

    /**
     * The case that has not appeared yet — and it has to say what it left behind, because a new binary
     * against the old curriculum is the same drift as a lane, one directory up.
     */
    public function test_no_sync_skips_it_and_says_so(): void
    {
        $composer = new FakeComposer(Release::measured(self::AFTER), installs: self::AFTER);
        $printed = '';

        $this->upgrade($composer, $printed, '--no-sync');

        $this->assertNotContains('sync', $composer->ran);
        $this->assertStringContainsString('--no-sync', $printed);
    }

    /**
     * The trap that has now cost this project four times: identity resolved from where the PROCESS happens
     * to be, when the process wanders. A worktree is its own git toplevel, so an upgrade run from inside a
     * lane must still move the PROJECT — not the lane it is standing in.
     */
    public function test_it_moves_the_project_even_when_run_from_inside_a_lane(): void
    {
        // Both halves of the trap at once: the process is standing in the lane AND the harness has
        // stamped the lane as the project, which is exactly what an agent working in one looks like.
        chdir($this->lane('alpha'));
        putenv('CLAUDE_PROJECT_DIR=' . $this->lane('alpha'));

        $composer = new FakeComposer(Release::measured(self::AFTER), installs: self::AFTER);

        $this->upgrade($composer);

        $this->assertSame([$this->root], $composer->roots, 'the lane was upgraded instead of the project');
    }

    /**
     * The board is session state, so a run from another session reads an empty one — and an empty board
     * means "nothing is held" only if it is the right board. The basis is printed rather than assumed,
     * because an unstated one is inherited as a fact.
     */
    public function test_it_says_which_board_the_holds_were_read_from(): void
    {
        $printed = '';
        $this->upgrade(new FakeComposer(Release::measured(self::AFTER), installs: self::AFTER), $printed);

        $this->assertStringContainsString('no board', $printed);

        $this->board()->claim('alpha', 'builder-a', '10:00');

        $printed = '';
        $this->upgrade(new FakeComposer(Release::measured(self::AFTER), installs: self::AFTER), $printed);

        $this->assertStringContainsString('holds read from the board of session', $printed);
    }

    /**
     * `sync` once deleted a project's own gitignore exception. That is fixed, but an `upgrade` that wraps
     * it inherits the whole class — so the guarantee is STATED where somebody would notice it being false.
     */
    public function test_it_states_what_it_promises_about_files_you_wrote(): void
    {
        $printed = '';
        $this->upgrade(new FakeComposer(Release::measured(self::AFTER), installs: self::AFTER), $printed);

        $this->assertStringContainsString('Nothing you wrote was replaced', $printed);
    }

    /**
     * Without a `lane.sh` there is nothing to re-run, and the lane stays behind. Said rather than left to
     * be discovered — a lane on an old vendor is exactly as wrong however it got there.
     */
    public function test_a_project_with_no_lane_script_is_told_its_lanes_stayed_behind(): void
    {
        unlink($this->root . '/.commandments/orchestrator/profiles/test/lane.sh');

        $printed = '';

        $this->assertSame(Console::REFUSED, $this->upgrade(new FakeComposer(Release::measured(self::AFTER), installs: self::AFTER), $printed));
        $this->assertStringContainsString('NOT refreshed', $printed);
        $this->assertSame('v0.9.0', $this->versionIn($this->lane('alpha')));
    }

    private function upgrade(FakeComposer $composer, string &$printed = '', string ...$flags): int
    {
        $out = fopen('php://memory', 'r+');
        $exit = new UpgradeCommand(console: new Console($out), composer: $composer)
            ->run(Input::fromArgv(['commandments', 'upgrade', ...$flags]));

        rewind($out);
        $printed = (string) stream_get_contents($out);

        return $exit;
    }

    private function board(): Board
    {
        return Board::inSession(Workspace::ofSession($this->root));
    }

    private function lane(string $name): string
    {
        return $this->root . '/.lanes/' . $name;
    }

    private function versionIn(string $checkout): string
    {
        return new Checkout($checkout)->version();
    }

    /**
     * A project with two lanes, as `lane open` would leave them: real worktrees of a real repository.
     */
    private function repository(): void
    {
        $git = 'git -C ' . escapeshellarg($this->root) . ' ';

        exec($git . 'init --initial-branch=main 2>&1');
        exec($git . 'config user.email t@example.com && ' . $git . 'config user.name Test');
        file_put_contents($this->root . '/README.md', "fixture\n");
        exec($git . 'add -A && ' . $git . 'commit -m first 2>&1');

        foreach (['alpha', 'beta'] as $name) {
            exec($git . 'worktree add -b ' . escapeshellarg($name) . ' ' . escapeshellarg($this->lane($name)) . ' main 2>&1');
        }
    }

    /**
     * The profile in force, with a `lane.sh` that does what a real one does: copy the project's vendor into
     * the lane. That is what makes the assertions about a lane's VERSION real rather than a claim that a
     * step was called.
     */
    private function profile(): void
    {
        $workspace = Workspace::ofSession($this->root);
        $folder = $workspace->shared(Workspace::ORCHESTRATOR . '/profiles/test');

        @mkdir($folder, 0777, true);
        file_put_contents($folder . '/profile.md', "# test\n");
        file_put_contents($folder . '/lane.sh', <<<SH
            #!/bin/sh
            mkdir -p "\$1/vendor/composer"
            cp {$this->root}/vendor/composer/installed.json "\$1/vendor/composer/installed.json"
            SH);

        Instance::inSession($workspace)->start('test', '10:00');
    }
}
