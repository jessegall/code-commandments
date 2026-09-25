<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cs;

use JesseGall\CodeCommandments\Cs\Bridge;
use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Support\HeldTool;
use PHPUnit\Framework\TestCase;

/**
 * A scan that meets C# with no bridge to read it says so — which image it needs and how that image is built — rather
 * than judging no C# in silence; a scan with no C# to read says nothing. Run as a real process, since STDERR cannot
 * be swapped in-process.
 */
final class MissingBridgeTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/missing-bridge-' . bin2hex(random_bytes(4));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->dir));
    }

    public function test_a_scan_that_finds_csharp_without_the_bridge_names_the_image_and_how_it_is_built(): void
    {
        file_put_contents("{$this->dir}/Cart.cs", "namespace Shop;\n\npublic sealed class Cart {}\n");

        [$files, $stderr] = $this->scanWithoutTheBridge();

        $this->assertSame('0', $files);
        $this->assertStringContainsString('1 C# file(s) left unread', $stderr);
        $this->assertStringContainsString(Bridge::image(), $stderr);
        $this->assertStringContainsString('docker build -t ' . Bridge::image(), $stderr);
        $this->assertStringContainsString('C# is not judged', $stderr);
    }

    public function test_a_scan_without_csharp_says_nothing_of_the_bridge(): void
    {
        file_put_contents("{$this->dir}/cart.php", "<?php\n");

        [$files, $stderr] = $this->scanWithoutTheBridge();

        $this->assertSame('0', $files);
        $this->assertSame('', $stderr);
    }

    /**
     * How many files a scan of the folder read, and what it wrote to STDERR, with a bridge that is never located.
     *
     * @return array{string, string}
     */
    private function scanWithoutTheBridge(): array
    {
        $autoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
        $script = sprintf(
            'require %s;
            final class NoBridge implements \\%s { public static function located(): \\JesseGall\\PhpTypes\\Option { return \\JesseGall\\PhpTypes\\Option::none(); } }
            echo \\%s::scan(%s, held: new \\%s(NoBridge::class))->fileCount();',
            var_export($autoload, true),
            \JesseGall\CodeCommandments\Support\LocatedTool::class,
            Codebase::class,
            var_export($this->dir, true),
            HeldTool::class,
        );

        $process = proc_open([PHP_BINARY, '-r', $script], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);

        foreach ($pipes as $pipe) {
            fclose($pipe);
        }

        proc_close($process);

        return [trim($stdout), $stderr];
    }
}
