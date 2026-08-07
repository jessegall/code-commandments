<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Support;

use JesseGall\CodeCommandments\Support\Directory;
use PHPUnit\Framework\TestCase;

/**
 * The one recursive copy/delete, and the SYMLINK cases that make a naive one dangerous.
 *
 * A recursive delete that treats a symlink as a directory does not delete the link — it deletes
 * what the link POINTS AT. That is not a hypothetical: the published skills are about to become
 * links into a real library, so a sweep of the link directory would empty the library instead.
 *
 * Every link this test creates has BOTH endpoints inside its own temp directory, asserted in
 * {@see setUp} — a link out of the tree plus a delete that follows links is how a test eats the
 * repository it is testing.
 */
final class DirectoryTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = (string) realpath(sys_get_temp_dir()) . '/cc-dir-' . uniqid('', true);
        mkdir($this->dir, 0775, true);
    }

    protected function tearDown(): void
    {
        // `rm -rf` unlinks a symlink rather than following it — do not replace this with the
        // subject under test.
        exec('rm -rf ' . escapeshellarg($this->dir));
    }

    public function test_it_deletes_a_tree(): void
    {
        $this->file('tree/nested/leaf.md', 'x');

        $this->assertTrue(Directory::delete("{$this->dir}/tree"));
        $this->assertDirectoryDoesNotExist("{$this->dir}/tree");
    }

    public function test_deleting_a_symlink_unlinks_it_and_leaves_the_target_whole(): void
    {
        $this->file('library/skill/SKILL.md', 'the real thing');
        $this->file('library/skill/reference/notes.md', 'also real');
        $this->link('link', 'library/skill');

        $this->assertTrue(Directory::delete("{$this->dir}/link"));

        $this->assertFalse(is_link("{$this->dir}/link"), 'the link itself is gone');
        $this->assertSame('the real thing', file_get_contents("{$this->dir}/library/skill/SKILL.md"));
        $this->assertSame('also real', file_get_contents("{$this->dir}/library/skill/reference/notes.md"));
    }

    public function test_it_removes_a_symlink_nested_inside_the_tree(): void
    {
        $this->file('target/keep.md', 'keep me');
        $this->file('tree/file.md', 'x');
        $this->link('tree/inner', 'target');

        $this->assertTrue(Directory::delete("{$this->dir}/tree"));

        $this->assertDirectoryDoesNotExist("{$this->dir}/tree");
        $this->assertSame('keep me', file_get_contents("{$this->dir}/target/keep.md"), 'a nested link is unlinked, never followed');
    }

    public function test_it_removes_a_dangling_symlink(): void
    {
        $this->file('gone/file.md', 'x');
        $this->link('dangling', 'gone');
        exec('rm -rf ' . escapeshellarg("{$this->dir}/gone"));

        // A dangling link is not `is_dir`, so a delete that leads with that check skips it forever
        // — and the next attempt to create the link fails because something already occupies it.
        $this->assertTrue(Directory::delete("{$this->dir}/dangling"));
        $this->assertFalse(is_link("{$this->dir}/dangling"));
    }

    public function test_deleting_what_is_not_there_is_a_no_op(): void
    {
        $this->assertTrue(Directory::delete("{$this->dir}/never-existed"));
    }

    public function test_it_copies_a_tree(): void
    {
        $this->file('from/SKILL.md', 'body');
        $this->file('from/reference/mechanics.md', 'detail');

        Directory::copy("{$this->dir}/from", "{$this->dir}/to");

        $this->assertSame('body', file_get_contents("{$this->dir}/to/SKILL.md"));
        $this->assertSame('detail', file_get_contents("{$this->dir}/to/reference/mechanics.md"));
    }

    private function file(string $path, string $contents): void
    {
        @mkdir(dirname("{$this->dir}/{$path}"), 0775, true);
        file_put_contents("{$this->dir}/{$path}", $contents);
    }

    /**
     * A link at $link pointing at $target, both stated relative to this test's own directory —
     * the containment that keeps a follow-the-link bug inside the sandbox.
     */
    private function link(string $link, string $target): void
    {
        $absolute = "{$this->dir}/{$target}";

        $this->assertStringStartsWith($this->dir . '/', (string) realpath($absolute), 'a test link must not escape its temp dir');

        symlink($absolute, "{$this->dir}/{$link}");
    }
}
