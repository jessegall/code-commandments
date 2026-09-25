<?php
namespace CodeCommandments\Record;

use JesseGall\CodeCommandments\Vue\Codebase;

/** Records every project a test builds a Vue codebase from, keyed by the test that built it. */
final class Recorder
{
    public static array $projects = [];

    public static function fromString(string $vue, string $path = 'component.vue'): Codebase
    {
        self::record([$path => $vue]);

        return Codebase::fromString($vue, $path);
    }

    public static function scan(string $dir, ...$rest): Codebase
    {
        $files = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)) as $file) {
            $files[substr($file->getPathname(), strlen($dir) + 1)] = file_get_contents($file->getPathname());
        }
        self::record($files);

        return Codebase::scan($dir, ...$rest);
    }

    private static function record(array $files): void
    {
        foreach (debug_backtrace() as $frame) {
            if (str_starts_with($frame['function'] ?? '', 'test_')) {
                self::$projects[$frame['function']][] = $files;

                return;
            }
        }
    }
}
