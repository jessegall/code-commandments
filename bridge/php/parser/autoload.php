<?php

/**
 * nikic/php-parser v5.7.0 (lib/ and LICENSE, unchanged), loaded whole before any scanned project's autoloader runs,
 * so a project carrying a php-parser of its own can never mix its classes into the bridge's.
 */

declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $file = __DIR__ . '/lib/' . str_replace('\\', '/', $class) . '.php';

    if (str_starts_with($class, 'PhpParser\\') && is_file($file)) {
        require $file;
    }
});

$classes = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__ . '/lib/PhpParser', FilesystemIterator::SKIP_DOTS));
foreach ($classes as $file) {
    if (ctype_upper($file->getFilename()[0])) {
        class_exists(str_replace('/', '\\', substr($file->getPathname(), strlen(__DIR__ . '/lib/'), -4)));
    }
}
