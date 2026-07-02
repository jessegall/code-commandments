<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli;

use JesseGall\CodeCommandments\Cli\JudgeReminder;
use PHPUnit\Framework\TestCase;

/**
 * The Stop-hook nudge fires ONCE per batch when judged files are touched, then stays silent — and
 * says nothing at all in a clean tree or outside a repo. Proven against a real temp git repo, since
 * the whole point is its reading of git state.
 */
final class JudgeReminderTest extends TestCase
{
    private string $repo;

    protected function setUp(): void
    {
        $this->repo = sys_get_temp_dir() . '/cc-judge-' . uniqid('', true);
        mkdir($this->repo);
        $this->git('init -q');
        $this->git('config user.email a@b.c');
        $this->git('config user.name test');
        $this->commit('README.md', '# repo'); // a HEAD to diff against
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->repo));
    }

    public function test_it_stays_silent_on_a_clean_tree(): void
    {
        $this->assertNull((new JudgeReminder)->reminder($this->repo));
    }

    public function test_it_nudges_once_for_a_touched_judged_file_then_goes_silent(): void
    {
        file_put_contents($this->repo . '/Service.php', "<?php\n");

        $first = (new JudgeReminder)->reminder($this->repo);
        $this->assertNotNull($first, 'a touched .php file earns a nudge');
        $this->assertStringContainsString('judge', $first);

        $this->assertNull((new JudgeReminder)->reminder($this->repo), 'the same batch is silent after one nudge');
    }

    public function test_it_ignores_non_judged_files(): void
    {
        file_put_contents($this->repo . '/notes.txt', 'hi');

        $this->assertNull((new JudgeReminder)->reminder($this->repo));
    }

    public function test_a_new_commit_opens_a_fresh_batch(): void
    {
        file_put_contents($this->repo . '/Service.php', "<?php\n");
        $this->assertNotNull((new JudgeReminder)->reminder($this->repo));
        $this->assertNull((new JudgeReminder)->reminder($this->repo), 'silent within the batch');

        // Commit → HEAD moves → a fresh batch, and a fresh change earns a fresh nudge.
        $this->git('add -A');
        $this->git('commit -q -m work');
        file_put_contents($this->repo . '/Other.vue', "<template></template>\n");

        $this->assertNotNull((new JudgeReminder)->reminder($this->repo), 'a new HEAD + new change nudges again');
    }

    public function test_it_is_silent_outside_a_repository(): void
    {
        $bare = sys_get_temp_dir() . '/cc-nonrepo-' . uniqid('', true);
        mkdir($bare);

        $this->assertNull((new JudgeReminder)->reminder($bare));

        rmdir($bare);
    }

    private function commit(string $file, string $content): void
    {
        file_put_contents($this->repo . '/' . $file, $content);
        $this->git('add -A');
        $this->git('commit -q -m init');
    }

    private function git(string $args): void
    {
        exec('git -C ' . escapeshellarg($this->repo) . ' ' . $args . ' 2>/dev/null');
    }
}
