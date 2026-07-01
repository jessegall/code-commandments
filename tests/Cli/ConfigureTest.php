<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli;

use JesseGall\CodeCommandments\Cli\Configure;
use JesseGall\CodeCommandments\Sins\Backend\Spatie\NonFinalData;
use JesseGall\CodeCommandments\Skills\Backend\ValueObjects;
use PHPUnit\Framework\TestCase;

/**
 * The `disable`/`enable` commands resolve a sin id and toggle it in `.commandments/config.php`
 * (via {@see \JesseGall\CodeCommandments\Cli\ConfigFile}). They run against the cwd, so each test
 * runs inside a throwaway project directory.
 */
final class ConfigureTest extends TestCase
{
    private string $dir;

    private string $cwd;

    protected function setUp(): void
    {
        $this->cwd = (string) getcwd();
        $this->dir = sys_get_temp_dir() . '/cc-configure-' . uniqid('', true);
        mkdir($this->dir, 0777, true);
        chdir($this->dir);
    }

    protected function tearDown(): void
    {
        chdir($this->cwd);
        @unlink($this->dir . '/.commandments/config.php');
        @rmdir($this->dir . '/.commandments');
        @rmdir($this->dir);
    }

    public function test_disable_then_enable_a_sin_by_name(): void
    {
        $this->assertSame(0, $this->exec('disable', 'non-final-data'));

        $config = (string) file_get_contents($this->dir . '/.commandments/config.php');
        $this->assertStringContainsString('$config->disable(\\' . NonFinalData::class . '::class)', $config);

        $this->assertSame(0, $this->exec('enable', 'non-final-data'));
        $config = (string) file_get_contents($this->dir . '/.commandments/config.php');
        $this->assertStringNotContainsString(NonFinalData::class, $config, 'enable removed it again');
    }

    public function test_disable_a_whole_skill_by_slug(): void
    {
        $this->assertSame(0, $this->exec('disable', 'value-objects'));

        $config = (string) file_get_contents($this->dir . '/.commandments/config.php');
        $this->assertStringContainsString('$config->disable(\\' . ValueObjects::class . '::class)', $config);

        $this->assertSame(0, $this->exec('enable', 'value-objects'));
        $config = (string) file_get_contents($this->dir . '/.commandments/config.php');
        $this->assertStringNotContainsString(ValueObjects::class, $config, 'enable removed the skill again');
    }

    public function test_an_unknown_sin_name_errors(): void
    {
        $this->assertSame(2, $this->exec('disable', 'no-such-sin-anywhere'));
        $this->assertFileDoesNotExist($this->dir . '/.commandments/config.php', 'nothing written for an unknown sin');
    }

    public function test_missing_argument_errors(): void
    {
        $this->assertSame(2, $this->exec('disable'));
    }

    private function exec(string $action, string ...$args): int
    {
        ob_start();
        $code = new Configure($action)->run($args);
        ob_get_clean();

        return $code;
    }
}
