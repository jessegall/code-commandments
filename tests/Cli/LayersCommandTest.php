<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli;

use JesseGall\CodeCommandments\Cli\Config\ConfigFile;
use JesseGall\CodeCommandments\Cli\Input;
use JesseGall\CodeCommandments\Cli\Layers\LayersCommand;
use PHPUnit\Framework\TestCase;

/**
 * `commandments layers add|allow` — the incremental moves (#409). A stack grows: a new namespace or
 * one new arrow must not cost a hand-deletion of the whole declared block.
 */
final class LayersCommandTest extends TestCase
{
    private string $root;

    private string $config;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/cc-layers-' . uniqid('', true);
        $this->config = $this->root . '/.commandments/config.php';
        mkdir($this->root . '/.commandments', 0777, true);
        chdir($this->root);

        file_put_contents($this->config, <<<'PHP_SOURCE'
        <?php

        use JesseGall\CodeCommandments\Config;
        use JesseGall\CodeCommandments\Detectors\Backend\NamespaceDependencyDetector;

        return function (Config $config): void {
            $config
                ->paths('src')

                ->configure(fn (NamespaceDependencyDetector $detector) => $detector
                    ->layer('App\\Ui\\Tokens')
                    ->layer('App\\Ui\\Elements', mayUse: ['App\\Ui\\Tokens']))

                ->disable();
        };
        PHP_SOURCE);
    }

    protected function tearDown(): void
    {
        chdir(dirname(__DIR__, 2));
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    private function exec(string ...$args): int
    {
        ob_start();
        $code = new LayersCommand()->run(Input::of('layers', $args));
        ob_get_clean();

        return $code;
    }

    private function layers(): array
    {
        return new ConfigFile($this->config)->layers();
    }

    public function test_add_declares_a_new_layer_with_its_arrows(): void
    {
        $this->assertSame(0, $this->exec('add', 'App\Domain', '--may-use=App\Ui\Tokens'));

        $this->assertSame(['App\Ui\Tokens'], $this->layers()['App\Domain'] ?? null);
    }

    public function test_add_widens_a_layer_that_is_already_declared(): void
    {
        $this->exec('add', 'App\Ui\Elements', '--may-use=App\Domain');

        $this->assertSame(['App\Ui\Tokens', 'App\Domain'], $this->layers()['App\Ui\Elements']);
    }

    public function test_add_is_idempotent(): void
    {
        $this->exec('add', 'App\Ui\Elements', '--may-use=App\Ui\Tokens');

        $this->assertSame(['App\Ui\Tokens'], $this->layers()['App\Ui\Elements'], 'the arrow is not doubled');
    }

    public function test_allow_adds_one_arrow(): void
    {
        $this->assertSame(0, $this->exec('allow', 'App\Ui\Tokens', 'App\Ui\Elements'));

        $this->assertSame(['App\Ui\Elements'], $this->layers()['App\Ui\Tokens']);
    }

    public function test_allow_refuses_an_undeclared_layer_rather_than_inventing_it(): void
    {
        $this->assertSame(2, $this->exec('allow', 'App\Typo', 'App\Ui\Tokens'));
        $this->assertArrayNotHasKey('App\Typo', $this->layers());
    }

    public function test_the_rest_of_the_config_is_left_exactly_as_it_was(): void
    {
        $before = (string) file_get_contents($this->config);

        $this->exec('allow', 'App\Ui\Tokens', 'App\Ui\Elements');

        $after = (string) file_get_contents($this->config);

        $this->assertStringContainsString("->paths('src')", $after);
        $this->assertStringContainsString('->disable();', $after);
        $this->assertSame(substr_count($before, "\n"), substr_count($after, "\n"), 'no line was added or lost');
        $this->assertStringContainsString("            ->layer('App\\\\Ui\\\\Tokens'", $after, 'the indentation is kept');
    }

    public function test_the_edited_config_is_still_valid_php(): void
    {
        $this->exec('add', 'App\Domain', '--may-use=App\Ui\Tokens,App\Ui\Elements');

        exec('php -l ' . escapeshellarg($this->config) . ' 2>&1', $output, $status);

        $this->assertSame(0, $status, implode("\n", $output));
    }

    public function test_add_without_a_namespace_reports_the_usage(): void
    {
        $this->assertSame(2, $this->exec('add'));
    }
}
