<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\ExcludedPaths;
use PHPUnit\Framework\TestCase;

/**
 * `exclude()` decides what a run never READS, not merely what it never reports — and in a monorepo
 * it can say so once, with a glob, instead of a line per sub-project.
 */
final class ExcludedPathsTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/cc-excluded-' . uniqid('', true);

        foreach (['platform/src', 'platform/public/build', 'mobile/src', 'mobile/dist', 'shared/dist'] as $dir) {
            @mkdir("{$this->root}/{$dir}", 0777, true);
            file_put_contents("{$this->root}/{$dir}/Thing.php", "<?php\nnamespace T;\nclass Thing {}\n");
        }
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    public function test_a_plain_entry_covers_the_folder_and_everything_under_it(): void
    {
        $excluded = ExcludedPaths::under($this->root, ['mobile/dist']);

        $this->assertTrue($excluded->covers("{$this->root}/mobile/dist"));
        $this->assertTrue($excluded->covers("{$this->root}/mobile/dist/Thing.php"));
        $this->assertFalse($excluded->covers("{$this->root}/mobile/src/Thing.php"));
    }

    public function test_a_glob_covers_every_sub_project_in_one_line(): void
    {
        // The monorepo case: one line instead of `mobile/dist`, `shared/dist`, … per project.
        $excluded = ExcludedPaths::under($this->root, ['*/dist']);

        $this->assertTrue($excluded->covers("{$this->root}/mobile/dist/Thing.php"));
        $this->assertTrue($excluded->covers("{$this->root}/shared/dist/Thing.php"));
        $this->assertFalse($excluded->covers("{$this->root}/mobile/src/Thing.php"));
    }

    public function test_a_double_star_reaches_any_depth(): void
    {
        $excluded = ExcludedPaths::under($this->root, ['**/build']);

        $this->assertTrue($excluded->covers("{$this->root}/platform/public/build/Thing.php"));
        $this->assertFalse($excluded->covers("{$this->root}/platform/src/Thing.php"));
    }

    public function test_a_single_star_stays_within_one_segment(): void
    {
        // `*/build` is one level down, so it must NOT reach platform/public/build.
        $excluded = ExcludedPaths::under($this->root, ['*/build']);

        $this->assertFalse($excluded->covers("{$this->root}/platform/public/build/Thing.php"));
    }

    public function test_an_excluded_tree_is_never_PARSED_not_merely_unreported(): void
    {
        // The bug this exists for: exclusion used to filter findings AFTER the whole tree had been
        // read and parsed, so a monorepo's build output cost the run its time and memory anyway.
        $all = Codebase::scan($this->root);
        $pruned = Codebase::scan($this->root, excluded: ExcludedPaths::under($this->root, ['*/dist', '**/build']));

        $this->assertNotNull($all->classNamed('T\Thing'), 'the fixture parses at all');
        $this->assertSame(5, count($all->whereClass()->get()), 'every Thing is read when nothing is excluded');
        // `*/dist` takes mobile and shared, `**/build` takes platform's — only the two src trees stay.
        $this->assertSame(2, count($pruned->whereClass()->get()), 'the three excluded trees were never read');
    }

    public function test_nothing_excluded_covers_nothing(): void
    {
        $this->assertTrue(new ExcludedPaths()->isEmpty());
        $this->assertFalse(new ExcludedPaths()->covers("{$this->root}/mobile/dist/Thing.php"));
    }
}
