<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli\Orchestration;

use JesseGall\CodeCommandments\Cli\Orchestration\UserAgents;
use JesseGall\CodeCommandments\Workspace;
use PHPUnit\Framework\TestCase;

/**
 * The user's agents folder is shared by every session on the machine, while a role name is chosen per
 * project. So the property that matters is not that publishing works — it is that two sessions
 * publishing the same role NAME do not land on one file, which is the shape that cost this build a
 * stash ref shared by nine worktrees and a scratchpad shared by every lane. Each was a namespace
 * addressed by a name that looked unique, with no error when two writers met.
 */
final class UserAgentsTest extends TestCase
{
    private string $home;

    private string $root;

    private string|false $priorConfig;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/cc-ua-' . uniqid('', true);
        $this->home = $this->root . '/home';
        mkdir($this->home, 0777, true);
        $this->priorConfig = getenv('CLAUDE_CONFIG_DIR');
        putenv('CLAUDE_CONFIG_DIR=' . $this->home);
    }

    protected function tearDown(): void
    {
        putenv($this->priorConfig === false ? 'CLAUDE_CONFIG_DIR' : 'CLAUDE_CONFIG_DIR=' . $this->priorConfig);
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    private function agents(string $session): UserAgents
    {
        return new UserAgents(Workspace::at($this->root, $session));
    }

    private function target(string $name): string
    {
        $path = $this->root . '/' . $name . '.md';
        file_put_contents($path, "# {$name}\n");

        return $path;
    }

    public function test_two_sessions_publishing_one_role_do_not_collide(): void
    {
        $a = $this->agents('sess-a')->publish('reviewer', $this->target('a'));
        $b = $this->agents('sess-b')->publish('reviewer', $this->target('b'));

        $this->assertNotSame(
            $a->unwrapOr(''),
            $b->unwrapOr(''),
            'two sessions published a `reviewer` and one silently replaced the other',
        );

        $this->assertSame("# a\n", file_get_contents($a->unwrapOr('')));
        $this->assertSame("# b\n", file_get_contents($b->unwrapOr('')));
    }

    /**
     * A link, so the profile stays the one source. Editing the generated file must be visible through it.
     */
    public function test_it_publishes_a_link_rather_than_a_copy(): void
    {
        $target = $this->target('reviewer');
        $link = $this->agents('sess-a')->publish('reviewer', $target)->unwrapOr('');

        file_put_contents($target, "# rewritten\n");

        $this->assertSame("# rewritten\n", file_get_contents($link), 'a copy would still read the old text');
    }

    /**
     * A sweep takes THIS session's and leaves every other session's standing.
     */
    public function test_a_sweep_takes_only_its_own(): void
    {
        $mine = $this->agents('sess-a')->publish('reviewer', $this->target('a'))->unwrapOr('');
        $theirs = $this->agents('sess-b')->publish('reviewer', $this->target('b'))->unwrapOr('');

        $this->assertSame(1, $this->agents('sess-a')->sweep());
        $this->assertFileDoesNotExist($mine);
        $this->assertFileExists($theirs, "another session's type must survive this session's cleanup");
    }
}
