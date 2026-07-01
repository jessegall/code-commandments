<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli;

use JesseGall\CodeCommandments\Cli\ConfigFile;
use PHPUnit\Framework\TestCase;

/**
 * {@see ConfigFile} edits the `$config->disable(...)` call through the AST engine (never text
 * scanning), so every edit here must keep the file valid PHP and round-trip through {@see
 * ConfigFile::disabled}.
 */
final class ConfigFileTest extends TestCase
{
    /** @var list<string> */
    private array $dirs = [];

    protected function tearDown(): void
    {
        foreach ($this->dirs as $dir) {
            @unlink($dir . '/.commandments/config.php');
            @rmdir($dir . '/.commandments');
            @rmdir($dir);
        }

        $this->dirs = [];
    }

    public function test_scaffold_writes_a_valid_config_with_an_empty_disable_call(): void
    {
        $file = $this->file();

        $this->assertTrue($file->scaffoldIfMissing());
        $this->assertFalse($file->scaffoldIfMissing(), 'written once');
        $this->assertValidPhp($file);
        $this->assertSame([], $file->disabled());
    }

    public function test_disable_adds_a_class_and_is_idempotent(): void
    {
        $file = $this->file();

        $this->assertTrue($file->disable('Demo\\Foo'));
        $this->assertFalse($file->disable('Demo\\Foo'), 'already disabled');

        $this->assertSame(['Demo\\Foo'], $file->disabled());
        $this->assertStringContainsString('$config->disable(\\Demo\\Foo::class)', $this->read($file));
        $this->assertValidPhp($file);
    }

    public function test_disable_appends_further_classes(): void
    {
        $file = $this->file();
        $file->disable('Demo\\Foo');
        $file->disable('Demo\\Bar');

        $this->assertSame(['Demo\\Foo', 'Demo\\Bar'], $file->disabled());
        $this->assertStringContainsString('$config->disable(\\Demo\\Foo::class, \\Demo\\Bar::class)', $this->read($file));
        $this->assertValidPhp($file);
    }

    public function test_enable_removes_a_class(): void
    {
        $file = $this->file();
        $file->disable('Demo\\Foo');
        $file->disable('Demo\\Bar');

        $this->assertTrue($file->enable('Demo\\Foo'));
        $this->assertSame(['Demo\\Bar'], $file->disabled());
        $this->assertValidPhp($file);
    }

    public function test_enable_the_last_class_leaves_an_empty_call(): void
    {
        $file = $this->file();
        $file->disable('Demo\\Foo');

        $this->assertTrue($file->enable('Demo\\Foo'));
        $this->assertSame([], $file->disabled());
        $this->assertStringContainsString('$config->disable()', $this->read($file));
        $this->assertValidPhp($file);
    }

    public function test_enable_a_class_that_is_not_disabled_is_a_no_op(): void
    {
        $file = $this->file();
        $file->scaffoldIfMissing();

        $this->assertFalse($file->enable('Demo\\Nope'));
    }

    public function test_a_leading_backslash_is_normalised(): void
    {
        $file = $this->file();
        $file->disable('\\Demo\\Foo');

        $this->assertSame(['Demo\\Foo'], $file->disabled());
        $this->assertFalse($file->disable('Demo\\Foo'), 'same class, with/without leading slash');
    }

    private function file(): ConfigFile
    {
        $dir = sys_get_temp_dir() . '/cc-cfgfile-' . uniqid('', true);
        mkdir($dir, 0777, true);
        $this->dirs[] = $dir;

        return ConfigFile::inProject($dir);
    }

    private function read(ConfigFile $file): string
    {
        return (string) file_get_contents($file->path);
    }

    private function assertValidPhp(ConfigFile $file): void
    {
        exec('php -l ' . escapeshellarg($file->path) . ' 2>&1', $out, $status);
        $this->assertSame(0, $status, "config is not valid PHP:\n" . implode("\n", $out));
    }
}
