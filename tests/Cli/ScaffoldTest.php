<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli;

use JesseGall\CodeCommandments\Cli\Scaffold;
use JesseGall\CodeCommandments\Sins\Catalog as Sins;
use PHPUnit\Framework\TestCase;

/**
 * End-to-end tests for the `scaffold` command: each writes a Laravel-style
 * `composer.json` (`"App\\": "app/"`) into a throwaway project, runs the command
 * from that cwd, and asserts the generated helper(s) — right path, injected
 * namespace, idempotent, dry-run touches nothing. Every temp project is removed.
 */
final class ScaffoldTest extends TestCase
{
    /** @var list<string> */
    private array $projects = [];

    private ?string $cwd = null;

    protected function tearDown(): void
    {
        if ($this->cwd !== null) {
            chdir($this->cwd);
            $this->cwd = null;
        }

        foreach ($this->projects as $dir) {
            $this->deleteDir($dir);
        }

        $this->projects = [];
    }

    public function test_renders_the_helper_at_its_path_with_the_injected_namespace(): void
    {
        $dir = $this->project();

        $this->scaffold(['--sin=nullable-callback']);

        $noop = $this->read($dir, 'app/Support/NoOp.php');
        $invokable = $this->read($dir, 'app/Support/Invokable.php');

        // Lands under the app root at the scaffold's sub-path…
        $this->assertFileExists($dir . '/app/Support/NoOp.php');
        $this->assertFileExists($dir . '/app/Support/Invokable.php');
        // …with the consumer's namespace (root + sub-path dirs) injected, not the placeholder.
        $this->assertStringContainsString('namespace App\Support;', $noop);
        $this->assertStringContainsString('namespace App\Support;', $invokable);
        $this->assertStringNotContainsString('{namespace}', $noop);
        // …and the generated PHP parses.
        $this->assertParses($dir . '/app/Support/NoOp.php');
        $this->assertParses($dir . '/app/Support/Invokable.php');
    }

    public function test_is_idempotent_and_never_overwrites_an_existing_file(): void
    {
        $dir = $this->project();

        $this->scaffold(['--sin=nullable-callback']);

        // Hand-edit the generated file, then re-run: the edit must survive (skipped).
        $target = $dir . '/app/Support/NoOp.php';
        file_put_contents($target, "<?php\n// hand edited\n");

        $this->scaffold(['--sin=nullable-callback']);

        $this->assertStringContainsString('// hand edited', (string) file_get_contents($target));
    }

    public function test_dry_run_writes_nothing(): void
    {
        $dir = $this->project();

        $this->scaffold(['--sin=nullable-callback', '--dry-run']);

        $this->assertFileDoesNotExist($dir . '/app/Support/NoOp.php');
        $this->assertFileDoesNotExist($dir . '/app/Support/Invokable.php');
    }

    public function test_a_frontend_scaffold_lands_under_the_js_root_without_a_namespace(): void
    {
        $dir = $this->project();

        $this->scaffold(['--sin=switch-case']);

        $target = $dir . '/resources/js/components/SwitchCase.vue';

        // Lands under the JS source root, not the PSR-4 app root…
        $this->assertFileExists($target);
        $this->assertFileDoesNotExist($dir . '/app/components/SwitchCase.vue');

        // …as the real Vue component, with no namespace placeholder to inject.
        $component = (string) file_get_contents($target);
        $this->assertStringContainsString('<template>', $component);
        $this->assertStringNotContainsString('{namespace}', $component);
    }

    public function test_a_sin_without_a_scaffold_generates_nothing(): void
    {
        $dir = $this->project();

        $this->scaffold(['--sin=array-bag']);

        $this->assertEmpty(glob($dir . '/app/*') ?: []);
    }

    public function test_every_declared_scaffold_renders_to_valid_php(): void
    {
        foreach (Sins::every() as $sin) {
            foreach ($sin->scaffolds() as $scaffold) {
                $code = $scaffold->render('App\\Support');
                $file = $this->tempFile($code);

                $this->assertParses($file, "scaffold for {$sin->name()} does not parse");
                @unlink($file);
            }
        }
    }

    /**
     * @param  list<string>  $args
     */
    private function scaffold(array $args): void
    {
        ob_start();
        $code = new Scaffold()->run($args);
        ob_get_clean();

        $this->assertSame(0, $code);
    }

    /**
     * A fresh temp project with a Laravel-style PSR-4 map, with cwd switched into it
     * (the command resolves the source root from `getcwd()`).
     */
    private function project(): string
    {
        $dir = sys_get_temp_dir() . '/cc-scaffold-' . uniqid('', true);
        mkdir($dir, 0777, true);
        $this->projects[] = $dir;

        file_put_contents($dir . '/composer.json', json_encode([
            'autoload' => ['psr-4' => ['App\\' => 'app/']],
        ]));

        $this->cwd ??= getcwd() ?: null;
        chdir($dir);

        return $dir;
    }

    private function tempFile(string $contents): string
    {
        $file = sys_get_temp_dir() . '/cc-stub-' . uniqid('', true) . '.php';
        file_put_contents($file, $contents);

        return $file;
    }

    private function assertParses(string $file, string $message = ''): void
    {
        exec('php -l ' . escapeshellarg($file) . ' 2>&1', $out, $status);
        $this->assertSame(0, $status, ($message !== '' ? $message . "\n" : '') . implode("\n", $out));
    }

    private function read(string $dir, string $name): string
    {
        return (string) file_get_contents($dir . '/' . $name);
    }

    private function deleteDir(string $dir): void
    {
        foreach (glob($dir . '/*') ?: [] as $file) {
            is_dir($file) ? $this->deleteDir($file) : @unlink($file);
        }

        @rmdir($dir);
    }
}
