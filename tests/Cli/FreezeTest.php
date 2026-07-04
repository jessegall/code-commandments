<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli;

use JesseGall\CodeCommandments\Ast\Support\Frozen;
use JesseGall\CodeCommandments\Cli\Freeze;
use JesseGall\CodeCommandments\Cli\Input;
use PHPUnit\Framework\TestCase;

final class FreezeTest extends TestCase
{
    private string $file;

    protected function setUp(): void
    {
        $this->file = sys_get_temp_dir() . '/cc-freeze-' . uniqid('', true) . '.php';
        file_put_contents($this->file, "<?php\n\ndeclare(strict_types=1);\n\nclass Migration {}\n");
    }

    protected function tearDown(): void
    {
        @unlink($this->file);
    }

    public function test_freeze_stamps_the_file_so_it_reads_as_frozen(): void
    {
        $code = new Freeze()->run(Input::of('freeze', [$this->file]));

        $this->assertSame(0, $code);
        $this->assertTrue(Frozen::isFrozen((string) file_get_contents($this->file)));
    }

    public function test_freeze_is_idempotent(): void
    {
        new Freeze()->run(Input::of('freeze', [$this->file]));
        $once = (string) file_get_contents($this->file);

        new Freeze()->run(Input::of('freeze', [$this->file]));
        $twice = (string) file_get_contents($this->file);

        $this->assertSame($once, $twice, 'freezing a frozen file changes nothing');
    }

    public function test_unfreeze_removes_the_stamp(): void
    {
        new Freeze()->run(Input::of('freeze', [$this->file]));

        $code = new Freeze()->run(Input::of('unfreeze', [$this->file]));

        $this->assertSame(0, $code);
        $this->assertFalse(Frozen::isFrozen((string) file_get_contents($this->file)));
    }

    public function test_a_missing_path_is_an_error(): void
    {
        $this->assertSame(2, new Freeze()->run(Input::of('freeze', ['/no/such/file.php'])));
    }
}
