<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Hooks;

use JesseGall\CodeCommandments\Hooks\Invocation;
use JesseGall\CodeCommandments\Hooks\ShellCommand;
use PHPUnit\Framework\TestCase;

/**
 * What a shell would actually run, and where. Both gates that judge a Bash call read this, and both of
 * them got one of these questions wrong on their own: one refused prose about a command, the other asked
 * its own process which worktree a merge would land in.
 */
final class ShellCommandTest extends TestCase
{
    /**
     * @return list<string>
     */
    private function linesOf(string $command, string $cwd = ''): array
    {
        return array_map(static fn (Invocation $i): string => $i->line, ShellCommand::of($command)->invocations($cwd));
    }

    public function test_a_cd_moves_everything_after_it(): void
    {
        $found = ShellCommand::of('cd /repo/.lanes/signals && git merge to-vue')->invocations('/repo');

        $this->assertCount(1, $found, 'the `cd` is not itself a command to judge');
        $this->assertSame('/repo/.lanes/signals', $found[0]->in);
    }

    public function test_a_relative_cd_resolves_against_where_the_shell_started(): void
    {
        $found = ShellCommand::of('cd .lanes/signals && git status')->invocations('/repo');

        $this->assertSame('/repo/.lanes/signals', $found[0]->in);
    }

    /**
     * `git -C` points one invocation at a checkout without moving anything, so the command after it is
     * back where the shell was.
     */
    public function test_git_dash_c_names_a_directory_for_one_invocation_only(): void
    {
        $found = ShellCommand::of('git -C /repo/.lanes/signals merge to-vue && git status')->invocations('/repo');

        $this->assertSame('/repo/.lanes/signals', $found[0]->in);
        $this->assertSame('/repo', $found[1]->in);
    }

    public function test_a_command_says_nothing_and_runs_where_the_shell_is(): void
    {
        $found = ShellCommand::of('git merge to-vue')->invocations('/repo');

        $this->assertSame('/repo', $found[0]->in);
    }

    /**
     * A bare `cd` goes home, and nothing here knows which home. Moving to a directory nobody named would
     * answer a question about a checkout with a guess.
     */
    public function test_a_bare_cd_moves_nothing(): void
    {
        $found = ShellCommand::of('cd && git status')->invocations('/repo');

        $this->assertSame('/repo', $found[0]->in);
    }

    /**
     * A separator inside a quoted string is not a separator — a shell would never start a command there.
     */
    public function test_a_quoted_string_is_not_a_command(): void
    {
        $lines = $this->linesOf('echo "git merge; git push"');

        $this->assertCount(1, $lines, 'the `;` inside the quotes starts nothing');
        $this->assertStringNotContainsString('merge', $lines[0], 'the words inside are blanked, the quotes kept');
    }

    /**
     * The other way a command carries prose. A commit message describing the very rule a gate enforces
     * was read as the commands it described.
     */
    public function test_a_heredoc_body_is_not_a_command(): void
    {
        $lines = $this->linesOf("git commit -F - <<'EOF'\nfix: the gate\n\ngit merge to-vue was refused\nEOF");

        $this->assertStringStartsWith('git commit', $lines[0]);
        $this->assertNotContains('git merge to-vue was refused', $lines);
    }

    public function test_a_heredoc_ends_at_its_delimiter(): void
    {
        $lines = $this->linesOf("cat <<EOF\ngit merge to-vue\nEOF\ngit status");

        $this->assertNotContains('git merge to-vue', $lines);
        $this->assertContains('git status', $lines, 'what follows the delimiter is a command again');
    }

    public function test_it_reads_git_however_the_invocation_is_spelled(): void
    {
        $this->assertTrue(new Invocation('git -C /lane merge to-vue', '/lane')->isGit('merge'));
        $this->assertTrue(new Invocation('git merge to-vue', '/lane')->isGit('merge'));
        $this->assertFalse(new Invocation('echo git merge', '/lane')->isGit('merge'), 'a command that names git is not git');
    }
}
