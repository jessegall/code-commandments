<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli\Scope;

use JesseGall\CodeCommandments\Cli\Scope\GitFiles;
use JesseGall\CodeCommandments\Tests\Concerns\TemporaryFolder;
use PHPUnit\Framework\TestCase;

/**
 * One `git status` answers both questions a commit-time hook asks — which commit HEAD is, and which judged
 * files changed on top of it — so the hook pays for one git process instead of three.
 */
final class WorkingTreeTest extends TestCase
{
    use TemporaryFolder;

    public function test_it_reads_head_and_every_kind_of_change_at_once(): void
    {
        $this->git('init -q');

        foreach (['Kept.php', 'Edited.php', 'Gone.php', 'Moved.php'] as $file) {
            file_put_contents("{$this->root}/{$file}", '<?php');
        }

        $this->git('add -A');
        $this->git('-c user.email=t@t -c user.name=t commit -q -m init');

        file_put_contents("{$this->root}/Edited.php", '<?php // edited');
        unlink("{$this->root}/Gone.php");
        $this->git('mv Moved.php Renamed.php');
        mkdir("{$this->root}/app/Deep Folder", 0777, true);
        file_put_contents("{$this->root}/app/Deep Folder/New Order.php", '<?php');
        file_put_contents("{$this->root}/Staged.php", '<?php');
        $this->git('add Staged.php');

        $tree = new GitFiles()->workingTree($this->root);
        $changed = array_map(fn (string $path): string => substr($path, strlen((string) realpath($this->root)) + 1), array_keys($tree->changed));
        sort($changed);

        $this->assertSame(trim((string) shell_exec('git -C ' . escapeshellarg($this->root) . ' rev-parse HEAD')), $tree->head);
        $this->assertSame(['Edited.php', 'Renamed.php', 'Staged.php', 'app/Deep Folder/New Order.php'], $changed);
    }

    public function test_a_repository_with_no_commit_has_no_head(): void
    {
        $this->git('init -q');
        file_put_contents("{$this->root}/First.php", '<?php');

        $tree = new GitFiles()->workingTree($this->root);

        $this->assertSame('', $tree->head);
        $this->assertCount(1, $tree->changed);
    }

    private function git(string $command): void
    {
        shell_exec('git -C ' . escapeshellarg($this->root) . ' ' . $command . ' 2>/dev/null');
    }
}
