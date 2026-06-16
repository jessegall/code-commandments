<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;

/**
 * Guard: no prophet or pipe may hand-roll a `/vendor/` substring check.
 *
 * `str_contains($file, '/vendor/')` is wrong for Composer's optimized
 * autoloader paths (see VendorPath). This test fails the moment a new prophet
 * reintroduces the naive check, so the whole class of "stopped flagging app
 * code" bugs can only be made once.
 */
class VendorPathUsageTest extends TestCase
{
    public function test_no_pipe_or_prophet_hand_rolls_the_vendor_check(): void
    {
        $roots = [
            __DIR__ . '/../../../src/Prophets',
            __DIR__ . '/../../../src/Support/Pipes',
        ];

        $offenders = [];

        foreach ($roots as $root) {
            /** @var \SplFileInfo $file */
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root)) as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                foreach (file($file->getPathname()) as $number => $line) {
                    $trimmed = ltrim($line);

                    // Skip comments — docblocks may legitimately mention vendor.
                    if (str_starts_with($trimmed, '*') || str_starts_with($trimmed, '//')) {
                        continue;
                    }

                    if (str_contains($line, "'/vendor/'") || str_contains($line, '"/vendor/"')) {
                        $offenders[] = $file->getPathname() . ':' . ($number + 1);
                    }
                }
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "These files hand-roll a `/vendor/` check — route it through "
            . "JesseGall\\CodeCommandments\\Support\\VendorPath::isVendor() instead "
            . "(Composer's optimized autoloader paths contain a literal /vendor/ even for app classes):\n"
            . implode("\n", $offenders)
        );
    }
}
