<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli\Orchestration;

use JesseGall\CodeCommandments\Cli\Orchestration\Checkout;

/**
 * Writes the one file a {@see Checkout} reads its version from — composer's own `installed.json`. A test
 * fixture rather than a stub: the reader under test is the real one, and what it reads is a real lockfile
 * in a real directory.
 */
final class Lockfile
{
    public static function write(string $checkout, string $version): void
    {
        $path = $checkout . '/vendor/composer';

        @mkdir($path, 0777, true);

        file_put_contents(
            $path . '/installed.json',
            json_encode(['packages' => [['name' => Checkout::PACKAGE, 'version' => $version]]]),
        );
    }
}
