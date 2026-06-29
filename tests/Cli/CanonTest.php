<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli;

use JesseGall\CodeCommandments\Cli\Canon;
use PHPUnit\Framework\TestCase;

final class CanonTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/canon_' . uniqid();
        mkdir($this->root, 0777, true);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    public function test_hydrates_from_psr4_skipping_scaffolding(): void
    {
        $this->dirs('app', 'src', 'database/factories');
        $this->composer(['App\\' => 'app/', 'Database\\Factories\\' => 'database/factories/']);

        $resolution = new Canon()->resolve($this->root);

        $this->assertTrue($resolution->hydrated);
        $this->assertSame(['app', 'src'], $this->basenames($resolution->paths));
        $this->assertFileExists($this->root . '/.commandments/backend.canon');
    }

    public function test_detects_app_and_src_by_convention_without_composer(): void
    {
        $this->dirs('app', 'src');

        $resolution = new Canon()->resolve($this->root);

        $this->assertSame(['app', 'src'], $this->basenames($resolution->paths));
    }

    public function test_reads_an_existing_canon_and_does_not_rehydrate(): void
    {
        $this->dirs('app', 'modules', '.commandments');
        file_put_contents(
            $this->root . '/.commandments/backend.canon',
            "# my canon\nmodules\n\n  app  \n",
        );

        $resolution = new Canon()->resolve($this->root);

        $this->assertFalse($resolution->hydrated);
        $this->assertSame(['modules', 'app'], $this->basenames($resolution->paths));
    }

    public function test_falls_back_to_the_project_root_when_nothing_is_detected(): void
    {
        $resolution = new Canon()->resolve($this->root);

        $this->assertSame([$this->root], $resolution->paths);
    }

    private function dirs(string ...$dirs): void
    {
        foreach ($dirs as $dir) {
            mkdir($this->root . '/' . $dir, 0777, true);
        }
    }

    /**
     * @param  array<string, string>  $psr4
     */
    private function composer(array $psr4): void
    {
        file_put_contents($this->root . '/composer.json', json_encode(['autoload' => ['psr-4' => $psr4]]));
    }

    /**
     * @param  list<string>  $paths
     * @return list<string>
     */
    private function basenames(array $paths): array
    {
        return array_map(static fn (string $path): string => basename($path), $paths);
    }
}
