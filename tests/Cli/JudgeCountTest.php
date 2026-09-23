<?php

namespace JesseGall\CodeCommandments\Tests\Cli;

use JesseGall\CodeCommandments\Tests\Concerns\TemporaryFolder;
use PHPUnit\Framework\TestCase;

final class JudgeCountTest extends TestCase
{
    use TemporaryFolder;

    protected function setUp(): void
    {
        mkdir($this->root . '/src');
        file_put_contents($this->root . '/src/A.php', "<?php\n\nfinal class A\n{\n}\n");
    }

    public function test_a_clean_run_says_how_many_files_it_judged(): void
    {
        file_put_contents($this->root . '/src/B.php', "<?php\n\nfinal class B\n{\n}\n");

        $this->assertStringContainsString('No sins found in 2 files', $this->judge());
    }

    public function test_the_count_takes_in_every_engine_that_judged(): void
    {
        file_put_contents($this->root . '/src/till.py', "def total(a, b):\n    return a + b\n");

        $this->assertStringContainsString('No sins found in 2 files', $this->judge());
    }

    private function judge(): string
    {
        return (string) shell_exec('cd ' . escapeshellarg($this->root) . ' && php ' . escapeshellarg(dirname(__DIR__, 2) . '/bin/commandments') . ' judge src --no-checklist 2>&1');
    }
}
