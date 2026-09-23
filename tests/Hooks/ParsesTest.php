<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Hooks;

use JesseGall\CodeCommandments\Hooks\Parses;
use JesseGall\CodeCommandments\Languages;
use JesseGall\CodeCommandments\Tests\Concerns\TemporaryFolder;
use PHPUnit\Framework\TestCase;

/**
 * A hook process parses a file once while it is unchanged, and again the moment it changes.
 */
final class ParsesTest extends TestCase
{
    use TemporaryFolder;

    public function test_an_unchanged_file_is_not_parsed_again(): void
    {
        $file = $this->root . '/till.py';
        file_put_contents($file, "def total(a, b):\n    return a + b\n");
        $parses = new Parses();

        $this->assertSame($parses->of($file, new Languages()), $parses->of($file, new Languages()));
    }

    public function test_a_changed_file_is_parsed_again(): void
    {
        $file = $this->root . '/Till.php';
        file_put_contents($file, "<?php\n\nfinal class Till\n{\n}\n");
        $parses = new Parses();
        $first = $parses->of($file, new Languages());

        file_put_contents($file, "<?php\n\nfinal class Till\n{\n    public int \$total = 0;\n}\n");

        $this->assertNotSame($first, $parses->of($file, new Languages()));
    }
}
