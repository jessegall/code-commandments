<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Support;

use JesseGall\CodeCommandments\Support\ScrollScope;
use PHPUnit\Framework\TestCase;

/**
 * {@see ScrollScope} is the single authority deciding whether a file belongs to a
 * scroll. Every git-derived candidate set passes through it, so an excluded /
 * out-of-path / wrong-extension file can NEVER be judged or resolved.
 */
class ScrollScopeTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . '/cc-scope-' . uniqid();
        mkdir($this->dir . '/src/Excluded', 0755, true);
        mkdir($this->dir . '/tests', 0755, true);
        mkdir($this->dir . '/vendor/pkg', 0755, true);

        file_put_contents($this->dir . '/src/InScope.php', "<?php\n");
        file_put_contents($this->dir . '/src/Excluded/Skip.php', "<?php\n");
        file_put_contents($this->dir . '/src/notphp.txt', "x\n");
        file_put_contents($this->dir . '/tests/OutOfScope.php', "<?php\n");
        file_put_contents($this->dir . '/vendor/pkg/Dep.php', "<?php\n");
    }

    protected function tearDown(): void
    {
        shell_exec('rm -rf ' . escapeshellarg($this->dir));
        parent::tearDown();
    }

    private function scope(): ScrollScope
    {
        return ScrollScope::fromConfig($this->dir, [
            'path' => 'src',
            'extensions' => ['php'],
            'exclude' => ['Excluded'],
        ]);
    }

    public function test_includes_only_in_scope_php_files(): void
    {
        $scope = $this->scope();

        $this->assertTrue($scope->includes($this->dir . '/src/InScope.php'));
        $this->assertFalse($scope->includes($this->dir . '/src/Excluded/Skip.php'), 'configured exclude');
        $this->assertFalse($scope->includes($this->dir . '/src/notphp.txt'), 'wrong extension');
        $this->assertFalse($scope->includes($this->dir . '/tests/OutOfScope.php'), 'outside scroll path');
    }

    public function test_default_excludes_apply_even_when_inside_the_path(): void
    {
        // vendor/ is a DEFAULT exclude — never judged even if it sits under the path.
        $scope = ScrollScope::fromConfig($this->dir, ['path' => '.', 'extensions' => ['php'], 'exclude' => []]);

        $this->assertFalse($scope->includes($this->dir . '/vendor/pkg/Dep.php'));
        $this->assertTrue($scope->includes($this->dir . '/src/InScope.php'));
    }

    public function test_filter_drops_out_of_scope_and_missing_files(): void
    {
        $kept = $this->scope()->filter([
            $this->dir . '/src/InScope.php',
            $this->dir . '/src/Excluded/Skip.php',
            $this->dir . '/tests/OutOfScope.php',
            $this->dir . '/src/DoesNotExist.php',
        ]);

        $this->assertSame([$this->dir . '/src/InScope.php'], $kept);
    }

    public function test_relative_path_resolves_against_base_not_cwd(): void
    {
        // The running tool has its OWN src/; a relative scroll path must resolve
        // against the project base, never the cwd.
        $scope = $this->scope();
        $this->assertTrue($scope->includes($this->dir . '/src/InScope.php'));
    }
}
