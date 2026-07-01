<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests;

use JesseGall\CodeCommandments\WorkingCopy;
use PHPUnit\Framework\TestCase;

/**
 * The overlay that lets `repent` converge in memory: it must read PENDING content over disk,
 * surface files a step created, and fold successive edits — the semantics the fixpoint (and the
 * scribes reading through it) depend on.
 */
final class WorkingCopyTest extends TestCase
{
    public function test_reads_pending_content_over_disk(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'wc-');
        file_put_contents($file, 'ON DISK');

        $overlay = new WorkingCopy([$file => 'PENDING']);

        $this->assertSame('PENDING', $overlay->read($file), 'the overlay content shadows disk');

        unlink($file);
    }

    public function test_falls_through_to_disk_when_not_overlaid(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'wc-');
        file_put_contents($file, 'ON DISK');

        $this->assertSame('ON DISK', new WorkingCopy()->read($file), 'an empty overlay is a transparent pass-through');
        $this->assertNull(new WorkingCopy()->read($file . '.nope'), 'an unreadable path is null');

        unlink($file);
    }

    public function test_surfaces_created_files_under_a_root_by_extension(): void
    {
        $root = sys_get_temp_dir();
        $created = $root . '/wc-new-' . uniqid() . '.vue';
        $php = $root . '/wc-new-' . uniqid() . '.php';

        $overlay = new WorkingCopy([$created => '<template/>', $php => '<?php', '/elsewhere/x.vue' => '']);

        $this->assertSame([$created], $overlay->createdUnder($root, '.vue'), 'only .vue under the root, not the .php or the outside path');
        $this->assertSame([$php], $overlay->createdUnder($root, '.php'));
    }

    public function test_does_not_surface_an_overlaid_file_that_exists_on_disk(): void
    {
        // An EDIT to a real file isn't a "created" file — the scan already finds it on disk;
        // surfacing it again would parse it twice.
        $file = tempnam(sys_get_temp_dir(), 'wc-') . '.php';
        file_put_contents($file, '<?php');

        $overlay = new WorkingCopy([$file => '<?php // edited']);

        $this->assertSame([], $overlay->createdUnder(dirname($file), '.php'));

        unlink($file);
    }

    public function test_with_folds_edits_and_later_wins(): void
    {
        $overlay = new WorkingCopy(['a' => '1', 'b' => '2']);

        $next = $overlay->with(['b' => '22', 'c' => '3']);

        $this->assertSame(['a' => '1', 'b' => '2'], $overlay->changes(), 'the original is untouched (immutable)');
        $this->assertSame(['a' => '1', 'b' => '22', 'c' => '3'], $next->changes(), 'a later edit to a path wins, new paths append');
    }
}
