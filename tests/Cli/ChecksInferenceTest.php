<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli;

use JesseGall\CodeCommandments\Cli\ChecksInference;
use PHPUnit\Framework\TestCase;

final class ChecksInferenceTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/cc-infer-' . uniqid('', true);
        @mkdir($this->dir, 0777, true);
    }

    protected function tearDown(): void
    {
        @unlink("{$this->dir}/composer.json");
        @unlink("{$this->dir}/package.json");
        @rmdir($this->dir);
    }

    public function test_it_picks_composer_scripts_by_category(): void
    {
        $this->writeComposer(['test' => 'phpunit', 'lint' => 'pint', 'analyse' => 'phpstan', 'serve' => 'x']);

        $this->assertSame(
            ['composer test', 'composer lint', 'composer analyse'],
            ChecksInference::detect($this->dir),
        );
    }

    public function test_first_match_per_category_wins(): void
    {
        $this->writeComposer(['phpunit' => 'x', 'test' => 'y', 'cs' => 'z']);

        // `test` beats `phpunit` (earlier in the group); `cs` is the lint pick.
        $this->assertSame(['composer test', 'composer cs'], ChecksInference::detect($this->dir));
    }

    public function test_it_combines_composer_and_npm(): void
    {
        $this->writeComposer(['test' => 'phpunit']);
        $this->writePackage(['test' => 'vitest', 'lint' => 'eslint']);

        $this->assertSame(
            ['composer test', 'npm run test', 'npm run lint'],
            ChecksInference::detect($this->dir),
        );
    }

    public function test_no_recognised_scripts_yields_empty(): void
    {
        $this->writeComposer(['serve' => 'x', 'build' => 'y']);

        $this->assertSame([], ChecksInference::detect($this->dir));
    }

    public function test_missing_manifests_yield_empty(): void
    {
        $this->assertSame([], ChecksInference::detect($this->dir));
    }

    /** @param array<string, string> $scripts */
    private function writeComposer(array $scripts): void
    {
        file_put_contents("{$this->dir}/composer.json", json_encode(['scripts' => $scripts]));
    }

    /** @param array<string, string> $scripts */
    private function writePackage(array $scripts): void
    {
        file_put_contents("{$this->dir}/package.json", json_encode(['scripts' => $scripts]));
    }
}
