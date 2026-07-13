<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli\Scope;

use JesseGall\CodeCommandments\Cli\Scope\Scope;
use JesseGall\CodeCommandments\Workspace;
use PHPUnit\Framework\TestCase;

/**
 * A project's {@see \JesseGall\CodeCommandments\Config::exclude} paths become an
 * {@see \JesseGall\CodeCommandments\Cli\Scope\ExcludedScope} compounded into every run's {@see Scope},
 * so a file under an excluded path is never a target — however the run was scoped.
 */
final class ScopeExcludedTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/cc-scope-excluded-' . uniqid('', true);
        mkdir($this->dir . '/src/Generated', 0777, true);
        mkdir($this->dir . '/src/Domain', 0777, true);
        mkdir($this->dir . '/.commandments', 0777, true);

        file_put_contents($this->excluded(), "<?php\nclass Generated {}\n");
        file_put_contents($this->live(), "<?php\nclass Order {}\n");
        file_put_contents(
            $this->dir . '/.commandments/config.php',
            "<?php\nuse JesseGall\\CodeCommandments\\Config;\n"
            . "return fn (Config \$c) => \$c->paths('src')->exclude('src/Generated');\n",
        );
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->dir));
    }

    public function test_a_file_under_an_excluded_path_is_out_of_scope(): void
    {
        $scope = Scope::fromArgs([], $this->dir, Workspace::at($this->dir));

        $this->assertFalse($scope->includes($this->excluded()), 'an excluded subtree is never a target');
        $this->assertTrue($scope->includes($this->live()), 'a sibling under the root stays in scope');
    }

    public function test_exclusion_wins_over_an_explicit_restriction(): void
    {
        // Even a run scoped to changed files can't pull an excluded file back in — the ExcludedScope ANDs.
        $scope = Scope::restrictedTo([$this->excluded(), $this->live()]);
        $excluding = Scope::fromArgs([], $this->dir, Workspace::at($this->dir));

        $this->assertTrue($scope->includes($this->excluded()), 'without config, a plain restriction admits it');
        $this->assertFalse($excluding->includes($this->excluded()), 'with the exclude() config, it is dropped');
    }

    public function test_a_prefix_match_respects_the_path_boundary(): void
    {
        // `src/Generated` must not swallow a sibling like `src/GeneratedReports`.
        mkdir($this->dir . '/src/GeneratedReports', 0777, true);
        $sibling = $this->dir . '/src/GeneratedReports/Report.php';
        file_put_contents($sibling, "<?php\nclass Report {}\n");

        $scope = Scope::fromArgs([], $this->dir, Workspace::at($this->dir));

        $this->assertTrue($scope->includes($sibling), 'a boundary match, not a raw string prefix');
    }

    private function excluded(): string
    {
        return $this->dir . '/src/Generated/Generated.php';
    }

    private function live(): string
    {
        return $this->dir . '/src/Domain/Order.php';
    }
}
