<?php

namespace JesseGall\CodeCommandments\Tests\Cli;

use PHPUnit\Framework\TestCase;

final class JudgeCountTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/cc-count-' . uniqid('', true);
        mkdir($this->dir . '/src', 0777, true);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->dir));
    }

    public function test_a_clean_run_says_how_many_files_it_judged(): void
    {
        file_put_contents($this->dir . '/src/A.php', "<?php\n\nfinal class A\n{\n}\n");
        file_put_contents($this->dir . '/src/B.php', "<?php\n\nfinal class B\n{\n}\n");

        $out = (string) shell_exec('cd ' . escapeshellarg($this->dir) . ' && php ' . escapeshellarg(dirname(__DIR__, 2) . '/bin/commandments') . ' judge src 2>&1');

        $this->assertStringContainsString('No sins found in 2 files', $out);
    }
}
