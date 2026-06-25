<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

use JesseGall\PhpTypes\T_String;

/**
 * Discovers the packaged prophet source files for a scroll type (Backend /
 * Frontend), yielding each FQCN paired with its absolute file path.
 */
final class ProphetFiles
{
    /**
     * @return iterable<array{0: string, 1: string}>  [fqcn, filePath]
     */
    public static function each(string $type): iterable
    {
        $dir = __DIR__ . '/../Prophets/' . $type;

        if (! is_dir($dir)) {
            return;
        }

        $files = scandir($dir);

        if ($files === false) {
            return;
        }

        foreach ($files as $file) {
            if (! str_ends_with($file, 'Prophet.php')) {
                continue;
            }

            $fqcn = 'JesseGall\\CodeCommandments\\Prophets\\' . $type . '\\' . str_replace('.php', T_String::EMPTY, $file);

            yield [$fqcn, $dir . '/' . $file];
        }
    }
}
