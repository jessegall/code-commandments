<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli;

use JesseGall\CodeCommandments\Cli\Config\ConfigScribe;
use JesseGall\CodeCommandments\Cli\Layers\LayersCommand;
use PHPUnit\Framework\TestCase;

final class LayersCommandTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/cc-layers-' . uniqid('', true);
        mkdir($this->dir, 0777, true);
    }

    protected function tearDown(): void
    {
        @unlink($this->dir . '/config.php');
        @rmdir($this->dir);
    }

    public function test_renders_a_declaration_in_dependency_order(): void
    {
        $rendered = LayersCommand::render(['App\Core' => [], 'App\Web' => ['App\Core']]);

        $this->assertSame(<<<'PHP'
                $config->configure(fn (NamespaceDependencyDetector $detector) => $detector
                    ->layer('App\\Core')
                    ->layer('App\\Web', mayUse: ['App\\Core']));
            PHP, $rendered);
    }

    public function test_writing_splices_the_declaration_and_its_import_leaving_the_rest_alone(): void
    {
        $this->config(<<<'PHP'
            <?php

            use JesseGall\CodeCommandments\Config;

            return function (Config $config): void {
                $config->paths('src');

                $config->disable(
                    // \Some\Sin::class,
                );
            };
            PHP);

        $written = new ConfigScribe($this->dir . '/config.php')->ensureLayers(LayersCommand::render(['App\Core' => []]));

        $this->assertTrue($written);

        $source = (string) file_get_contents($this->dir . '/config.php');

        $this->assertStringContainsString('use JesseGall\CodeCommandments\Detectors\Backend\NamespaceDependencyDetector;', $source);
        $this->assertStringContainsString("->layer('App\\\\Core'));", $source);
        $this->assertStringContainsString("// \\Some\\Sin::class,", $source, "the human's own lines survive");
        $this->assertStringContainsString("\$config->paths('src');", $source);
    }

    public function test_a_config_that_already_declares_layers_is_never_overwritten(): void
    {
        // The stack is the human's to decide; this command proposes, it does not seize.
        $this->config(<<<'PHP'
            <?php

            use JesseGall\CodeCommandments\Config;

            return function (Config $config): void {
                $config->configure(fn ($d) => $d->layer('App\\Mine'));

                $config->disable();
            };
            PHP);

        $before = (string) file_get_contents($this->dir . '/config.php');

        $this->assertFalse(new ConfigScribe($this->dir . '/config.php')->ensureLayers(LayersCommand::render(['App\Core' => []])));
        $this->assertSame($before, file_get_contents($this->dir . '/config.php'));
    }

    private function config(string $source): void
    {
        file_put_contents($this->dir . '/config.php', $source);
    }
}
