<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli\Scope;

use JesseGall\CodeCommandments\Cli\Scope\GitFiles;
use JesseGall\CodeCommandments\Language;
use PHPUnit\Framework\TestCase;

/**
 * A scoped judge (`--changes`, `--branch`) sees a changed file of EVERY language judge reads — a
 * `.ts` module included, which the scope used to drop because its own list of extensions had
 * fallen behind the engines'.
 */
final class GitFilesJudgedTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/git-files-judged-' . uniqid();
        mkdir($this->root);
        $this->git('init -q');
        $this->git('-c user.email=t@t -c user.name=t commit -q --allow-empty -m init');
    }

    protected function tearDown(): void
    {
        shell_exec('rm -rf ' . escapeshellarg($this->root));
    }

    public function test_a_changed_file_of_every_judged_language_is_in_scope(): void
    {
        foreach (['Order.php', 'OrderCard.vue', 'orders.ts', 'notes.md'] as $file) {
            file_put_contents("{$this->root}/{$file}", 'x');
        }

        $changed = array_map('basename', array_keys(new GitFiles()->changedVsHead($this->root)));
        sort($changed);

        $this->assertSame(['Order.php', 'OrderCard.vue', 'orders.ts'], $changed);
    }

    public function test_every_language_names_the_files_it_is_written_in(): void
    {
        $this->assertTrue(Language::judges('app/Order.php'));
        $this->assertTrue(Language::judges('resources/js/OrderCard.vue'));
        $this->assertTrue(Language::judges('resources/js/orders.ts'));
        $this->assertFalse(Language::judges('README.md'));
        $this->assertFalse(Language::judges('resources/js/orders.tsx.bak'));
    }

    private function git(string $command): void
    {
        shell_exec('git -C ' . escapeshellarg($this->root) . ' ' . $command . ' 2>/dev/null');
    }
}
