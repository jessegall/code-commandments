<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli\Scope;

use JesseGall\CodeCommandments\Cli\Scope\Scope;
use PHPUnit\Framework\TestCase;

/**
 * A {@see \JesseGall\CodeCommandments\Cli\Scope\FrozenScope} is compounded into every scope, so a frozen
 * file is never a target and a chain touching one is dropped whole.
 */
final class ScopeFrozenTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/cc-scope-frozen-' . uniqid('', true);
        mkdir($this->dir);
        file_put_contents($this->frozen(), "<?php\n#[Frozen]\nclass Migration {}\n");
        file_put_contents($this->live(), "<?php\nclass Service {}\n");
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->dir));
    }

    public function test_a_frozen_file_is_out_of_scope_but_a_normal_one_is_in(): void
    {
        $scope = Scope::everything();

        $this->assertFalse($scope->includes($this->frozen()), 'frozen files are never a target');
        $this->assertTrue($scope->includes($this->live()));
    }

    public function test_a_frozen_file_is_excluded_even_from_a_restricted_scope(): void
    {
        // Explicitly restricting to the frozen file still excludes it — frozen wins over selection.
        $scope = Scope::restrictedTo([$this->frozen(), $this->live()]);

        $this->assertFalse($scope->includes($this->frozen()));
        $this->assertTrue($scope->includes($this->live()));
    }

    public function test_permits_drops_a_chain_that_touches_a_frozen_file(): void
    {
        $scope = Scope::everything();

        $this->assertTrue($scope->permits([$this->live()]), 'a lone in-scope file is fine');
        $this->assertFalse($scope->permits([$this->live(), $this->frozen()]), 'a chain into a frozen file is dropped whole');
    }

    public function test_permits_allows_a_freshly_created_file(): void
    {
        $scope = Scope::everything();
        $newFile = $this->dir . '/Extracted.php'; // does not exist yet

        $this->assertTrue($scope->permits([$this->live(), $newFile]), 'a new file an extractor creates is allowed');
    }

    private function frozen(): string
    {
        return $this->dir . '/Migration.php';
    }

    private function live(): string
    {
        return $this->dir . '/Service.php';
    }
}
