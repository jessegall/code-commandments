<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli;

use JesseGall\CodeCommandments\Cli\Config\ConfigFile;
use JesseGall\CodeCommandments\Cli\Config\SourceRoots;
use JesseGall\CodeCommandments\Language;
use JesseGall\CodeCommandments\Tests\Concerns\TemporaryFolder;
use PHPUnit\Framework\TestCase;

/**
 * {@see SourceRoots} resolves the scan roots from `config.php` — auto-detecting them from
 * composer.json on first run and scaffolding a `$config->paths(...)` into the config.
 */
final class SourceRootsTest extends TestCase
{
    use TemporaryFolder;

    public function test_detects_from_psr4_skipping_scaffolding_and_writes_config(): void
    {
        $this->dirs('app', 'src', 'database/factories');
        $this->composer(['App\\' => 'app/', 'Database\\Factories\\' => 'database/factories/']);

        $roots = new SourceRoots()->resolve($this->root, false);

        $this->assertSame(['app', 'src'], $this->basenames($roots));
        $this->assertFileExists($this->root . '/.commandments/config.php');
        $this->assertSame(['app', 'src'], ConfigFile::inProject($this->root)->paths());
    }

    public function test_detects_app_and_src_by_convention_without_composer(): void
    {
        $this->dirs('app', 'src');

        $roots = new SourceRoots()->resolve($this->root, false);

        $this->assertSame(['app', 'src'], $this->basenames($roots));
    }

    public function test_reads_declared_paths_from_config_and_does_not_redetect(): void
    {
        $this->dirs('app', 'modules', '.commandments');
        file_put_contents(
            $this->root . '/.commandments/config.php',
            "<?php\nuse JesseGall\\CodeCommandments\\Config;\nreturn function (Config \$config): void {\n    \$config->paths('modules', 'app');\n    \$config->disable();\n};\n",
        );

        $roots = new SourceRoots()->resolve($this->root, false);

        $this->assertSame(['modules', 'app'], $this->basenames($roots));
    }

    public function test_fills_an_existing_config_with_an_empty_paths_call(): void
    {
        $this->dirs('app', 'src', '.commandments');
        ConfigFile::inProject($this->root)->scaffoldIfMissing(); // empty paths()

        $roots = new SourceRoots()->resolve($this->root, false);

        $this->assertSame(['app', 'src'], $this->basenames($roots));
        $this->assertSame(['app', 'src'], ConfigFile::inProject($this->root)->paths());
    }

    public function test_an_explicit_path_is_used_verbatim(): void
    {
        $roots = new SourceRoots()->resolve($this->root . '/some/dir', true);

        $this->assertSame([$this->root . '/some/dir'], $roots);
    }

    public function test_falls_back_to_the_project_root_when_nothing_is_detected(): void
    {
        $roots = new SourceRoots()->resolve($this->root, false);

        $this->assertSame([$this->root], $roots);
    }

    public function test_a_frontend_app_beside_the_backend_is_found_and_its_build_left_out(): void
    {
        $this->dirs('app', 'vendor', 'web/src', 'web/dist', 'web/node_modules');
        file_put_contents($this->root . '/web/package.json', '{}');

        $roots = new SourceRoots();

        $this->assertSame(['app', 'web/src'], $roots->detect($this->root));
        $this->assertSame(['vendor', 'web/dist', 'web/node_modules'], $roots->built($this->root), 'what an installer or a build wrote is left out');

        file_put_contents($this->root . '/web/src/App.vue', '<template><div /></template>');
        mkdir($this->root . '/web/node_modules/lib', 0777, true);
        file_put_contents($this->root . '/web/node_modules/lib/index.ts', 'export {}');

        $this->assertTrue($roots->writes($this->root, Language::Vue), 'a .vue file in a source folder is Vue written');
        $this->assertFalse($roots->writes($this->root, Language::TypeScript), 'a .ts file only under node_modules is not');
        $this->assertFalse($roots->writes($this->root, Language::Php), 'no .php file, no PHP');
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
