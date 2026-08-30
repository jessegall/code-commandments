<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Support;

use JesseGall\CodeCommandments\Support\File;
use PHPUnit\Framework\TestCase;

/**
 * The write that never leaves a user's file in pieces. What can be asserted here is what the
 * atomicity is BUILT from — a rename over the target, so the old contents stand until the new ones
 * are complete on disk, the mode is carried across, and a failure changes nothing.
 */
final class FileTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = (string) realpath(sys_get_temp_dir()) . '/cc-file-' . uniqid('', true);
        mkdir($this->dir, 0775, true);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->dir));
    }

    public function test_it_writes(): void
    {
        $this->assertTrue(File::write("{$this->dir}/new.md", "hello\n"));
        $this->assertSame("hello\n", file_get_contents("{$this->dir}/new.md"));
    }

    public function test_it_keeps_an_existing_files_permissions(): void
    {
        $path = "{$this->dir}/existing.md";
        file_put_contents($path, 'before');
        chmod($path, 0640);

        File::write($path, 'after');

        $this->assertSame('after', file_get_contents($path));
        $this->assertSame(0640, fileperms($path) & 0777, 'a rename must not widen or narrow what was there');
    }

    public function test_it_leaves_no_temporary_file_behind(): void
    {
        File::write("{$this->dir}/thing.md", 'x');

        $this->assertSame(['thing.md'], array_values(array_diff(scandir($this->dir) ?: [], ['.', '..'])));
    }

    /**
     * A write MAKES its folder. Every caller otherwise carries the same two lines before every write, and
     * the one that forgets loses the write silently.
     */
    public function test_a_write_into_a_missing_folder_makes_it(): void
    {
        $path = "{$this->dir}/nested/deeper/file.md";

        $this->assertTrue(File::write($path, 'x'));
        $this->assertSame('x', file_get_contents($path));
    }

    /**
     * And where it genuinely cannot land, the failure is REPORTED rather than swallowed — here the parent
     * is a FILE, so no folder can be made in its place.
     */
    public function test_a_write_that_cannot_land_says_so(): void
    {
        file_put_contents("{$this->dir}/blocker", 'i am not a folder');

        $path = "{$this->dir}/blocker/file.md";

        $this->assertFalse(File::write($path, 'x'));
        $this->assertFileDoesNotExist($path);
    }
}
