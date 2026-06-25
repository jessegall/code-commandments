<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Pilgrimage;

use JesseGall\CodeCommandments\Support\Pilgrimage\PilgrimageRunner;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * The git-derived pilgrimage scope (branch/staged) must be restricted to the
 * scroll's `path`/`extensions`/`exclude` — exactly as `judge --git/--staged` and
 * the absolve/report resolvers are. Otherwise the walk surfaces findings in files
 * OUTSIDE the scroll (an excluded `tests/` tree, a non-`.php` file) that the gate
 * and the absolve/report resolvers can't see, wedging the agent on a finding it
 * can neither fix in scope nor absolve. Regression for that mismatch.
 */
class PilgrimageScrollScopeTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . '/cc-pilgscope-' . uniqid();
        mkdir($this->dir . '/src/Nested', 0755, true);
        mkdir($this->dir . '/src/Excluded', 0755, true);
        mkdir($this->dir . '/tests/Suite', 0755, true);

        file_put_contents($this->dir . '/src/InScope.php', "<?php\n");
        file_put_contents($this->dir . '/src/Nested/AlsoInScope.php', "<?php\n");
        file_put_contents($this->dir . '/src/Excluded/Skip.php', "<?php\n");
        file_put_contents($this->dir . '/tests/Suite/OutOfScope.php', "<?php\n");
        file_put_contents($this->dir . '/src/notphp.txt', "x\n");
    }

    protected function tearDown(): void
    {
        shell_exec('rm -rf ' . escapeshellarg($this->dir));
        parent::tearDown();
    }

    /**
     * @param  list<string>  $files
     * @return list<string>
     */
    private function filterToScroll(array $files): array
    {
        $runner = new PilgrimageRunner($this->dir, ['scrolls' => ['backend' => [
            'path' => 'src',
            'extensions' => ['php'],
            'exclude' => ['Excluded'],
            'prophets' => [],
        ]]], 'backend');

        $method = new ReflectionMethod($runner, 'filterToScroll');

        return $method->invoke($runner, $files);
    }

    public function test_restricts_git_files_to_the_scroll_path_extensions_and_exclude(): void
    {
        $kept = $this->filterToScroll([
            $this->dir . '/src/InScope.php',
            $this->dir . '/src/Nested/AlsoInScope.php',
            $this->dir . '/src/Excluded/Skip.php',     // excluded
            $this->dir . '/tests/Suite/OutOfScope.php', // outside scroll path
            $this->dir . '/src/notphp.txt',             // wrong extension
        ]);

        $this->assertContains($this->dir . '/src/InScope.php', $kept);
        $this->assertContains($this->dir . '/src/Nested/AlsoInScope.php', $kept);
        $this->assertNotContains($this->dir . '/src/Excluded/Skip.php', $kept);
        $this->assertNotContains($this->dir . '/tests/Suite/OutOfScope.php', $kept);
        $this->assertNotContains($this->dir . '/src/notphp.txt', $kept);
    }
}
