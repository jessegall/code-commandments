<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli\Orchestration;

use JesseGall\CodeCommandments\Cli\Console;
use JesseGall\CodeCommandments\Cli\Input;
use JesseGall\CodeCommandments\Cli\Orchestration\Binding;
use JesseGall\CodeCommandments\Cli\Orchestration\BuildCommand;
use JesseGall\CodeCommandments\Cli\Orchestration\Roles;
use JesseGall\CodeCommandments\Tests\Cli\CapturingHookIO;
use JesseGall\CodeCommandments\Tests\Cli\FakeGit;
use JesseGall\CodeCommandments\Workspace;
use PHPUnit\Framework\TestCase;

/**
 * A role holds exactly ONE agent. The failure this exists for came out of a real build: an agent id dies
 * with its session, both standing agents died mid-build, and binding fresh ones left `roles` listing two
 * integrators and two auditors with nothing to say which was current — so the check that resolves a role
 * to an agent could be answered by a corpse.
 */
final class RolesTest extends TestCase
{
    private string $root;

    private string|false $priorProjectDir;

    private string|false $priorSession;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/cc-roles-' . uniqid('', true);
        mkdir($this->root . '/.commandments', 0777, true);
        $this->priorProjectDir = getenv('CLAUDE_PROJECT_DIR');
        $this->priorSession = getenv('CLAUDE_CODE_SESSION_ID');
        putenv('CLAUDE_PROJECT_DIR=' . $this->root);
        putenv('CLAUDE_CODE_SESSION_ID=roles-test');
    }

    protected function tearDown(): void
    {
        putenv($this->priorProjectDir === false ? 'CLAUDE_PROJECT_DIR' : 'CLAUDE_PROJECT_DIR=' . $this->priorProjectDir);
        putenv($this->priorSession === false ? 'CLAUDE_CODE_SESSION_ID' : 'CLAUDE_CODE_SESSION_ID=' . $this->priorSession);
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    private function workspace(): Workspace
    {
        return Workspace::ofSession($this->root); // The same folder `commandments build` reads.
    }

    private function build(string ...$argv): string
    {
        $out = fopen('php://memory', 'r+');

        new BuildCommand(new CapturingHookIO(new FakeGit($this->root)), new Console($out))
            ->run(Input::fromArgv(['commandments', 'build', ...$argv]));

        rewind($out);

        return (string) stream_get_contents($out);
    }

    private function roles(): Roles
    {
        return Roles::inSession($this->workspace());
    }

    public function test_a_role_is_read_back_from_the_agent_it_was_given_to(): void
    {
        $this->roles()->assign('a5d4', 'integrator');

        $this->assertSame('integrator', $this->roles()->of('a5d4')->unwrap());
        $this->assertSame('a5d4', $this->roles()->agentFor('integrator')->unwrap());
    }

    /**
     * The reported failure, in the shape it happened: the same role bound twice.
     */
    public function test_assigning_a_role_twice_leaves_one_current_binding(): void
    {
        $roles = $this->roles();
        $roles->assign('a5d4', 'integrator');
        $roles->assign('a4d2', 'integrator');

        $this->assertCount(1, $this->roles()->all());
        $this->assertSame('a4d2', $this->roles()->agentFor('integrator')->unwrap(), 'the second id is the one that resolves');
    }

    public function test_the_replaced_agent_no_longer_holds_the_role(): void
    {
        $roles = $this->roles();
        $roles->assign('a5d4', 'integrator');
        $roles->assign('a4d2', 'integrator');

        $this->assertTrue($this->roles()->of('a5d4')->isNone(), 'a replaced agent holds nothing');
        $this->assertSame('integrator', $this->roles()->of('a4d2')->unwrap());
    }

    /**
     * The replacement is said at the moment it happens, to the person who did it — the one place a
     * reader needs it, and the listing is left carrying only what is current.
     */
    public function test_an_assignment_names_the_agent_it_replaced(): void
    {
        $this->build('assign', 'integrator', '--to=a5d4');

        $this->assertStringContainsString('replaces a5d4', $this->build('assign', 'integrator', '--to=a4d2'));
    }

    public function test_a_role_bound_for_the_first_time_replaces_nobody(): void
    {
        $this->assertStringNotContainsString('replaces', $this->build('assign', 'integrator', '--to=a5d4'));
    }

    public function test_rebinding_a_role_to_the_agent_that_holds_it_changes_nothing(): void
    {
        $roles = $this->roles();
        $roles->assign('a5d4', 'integrator');
        $roles->assign('a5d4', 'integrator');

        $this->assertCount(1, $this->roles()->all());
        $this->assertSame('a5d4', $this->roles()->agentFor('integrator')->unwrap());
    }

    public function test_every_role_keeps_its_own_agent(): void
    {
        $roles = $this->roles();
        $roles->assign('a5d4', 'integrator');
        $roles->assign('ade6', 'auditor');
        $roles->assign('a4d2', 'integrator');

        $this->assertCount(2, $this->roles()->all());
        $this->assertSame('a4d2', $this->roles()->agentFor('integrator')->unwrap());
        $this->assertSame('ade6', $this->roles()->agentFor('auditor')->unwrap());
    }

    /**
     * An agent holds one role too — giving it another moves it rather than leaving it answering for both.
     */
    public function test_an_agent_given_a_second_role_stops_holding_the_first(): void
    {
        $roles = $this->roles();
        $roles->assign('a5d4', 'integrator');
        $roles->assign('a5d4', 'auditor');

        $this->assertSame('auditor', $this->roles()->of('a5d4')->unwrap());
        $this->assertTrue($this->roles()->agentFor('integrator')->isNone());
    }

    public function test_an_unassigned_role_and_an_unassigned_agent_are_absent(): void
    {
        $this->roles()->assign('a5d4', 'integrator');

        $this->assertTrue($this->roles()->agentFor('auditor')->isNone());
        $this->assertTrue($this->roles()->of('ade6')->isNone());
        $this->assertTrue($this->roles()->of('')->isNone());
    }

    /**
     * A file written by the version that ACCUMULATED — the exact table the report carried. Nothing
     * migrates it, so reading has to answer with the newest binding rather than the first one it meets.
     */
    public function test_a_file_that_accumulated_reads_as_the_newest_binding_per_role(): void
    {
        $this->writeRoleLines(
            "a5d4\tintegrator",
            "ade6\tauditor",
            "a4d2\tintegrator",
            "ae44\tauditor",
        );

        $this->assertCount(2, $this->roles()->all());
        $this->assertSame('a4d2', $this->roles()->agentFor('integrator')->unwrap());
        $this->assertSame('ae44', $this->roles()->agentFor('auditor')->unwrap());
        $this->assertTrue($this->roles()->of('a5d4')->isNone(), 'the superseded id answers for nothing');
    }

    /**
     * And the superseded lines go on the next write, rather than travelling forward forever.
     */
    public function test_the_next_assignment_clears_what_a_file_had_accumulated(): void
    {
        $this->writeRoleLines("a5d4\tintegrator", "a4d2\tintegrator");

        $this->roles()->assign('abcd', 'integrator');

        $this->assertCount(1, $this->roles()->all());
        $this->assertSame('abcd', $this->roles()->agentFor('integrator')->unwrap());
    }

    public function test_a_binding_survives_a_write_and_a_read(): void
    {
        $read = Binding::fromLine(new Binding('a4d2', 'integrator')->toLine())->unwrap();

        $this->assertSame('a4d2', $read->agent);
        $this->assertSame('integrator', $read->role);
        $this->assertTrue(Binding::fromLine('nothing-here')->isNone());
    }

    /**
     * The listing a person reads. One line per role — and no word that says the agent is still there,
     * because nothing here reached it.
     */
    public function test_the_listing_shows_one_line_per_role(): void
    {
        $this->build('assign', 'integrator', '--to=a5d4');
        $this->build('assign', 'integrator', '--to=a4d2');

        $listing = $this->build('roles');

        $this->assertSame(1, substr_count($listing, 'integrator  '), 'one line per role');
        $this->assertStringContainsString('a4d2', $listing);
        $this->assertStringNotContainsString('a5d4', $listing, 'the id it replaced is not a second answer');
        $this->assertStringContainsString('nothing here reaches an agent', $listing);
    }

    /**
     * Writes the list section by hand, as the version that accumulated left it.
     */
    private function writeRoleLines(string ...$lines): void
    {
        $file = $this->workspace()->path('.roles');
        mkdir(dirname($file), 0777, true);
        file_put_contents($file, implode("\n", ['-----', ...$lines, '-----', 'legend']) . "\n");
    }
}
