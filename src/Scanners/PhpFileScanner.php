<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Scanners;

/**
 * Scanner specialized for PHP files.
 */
class PhpFileScanner extends GenericFileScanner
{
    public function scan(string $path, array $extensions = [], array $excludePaths = []): iterable
    {
        // Default to PHP files if no extensions specified
        if (empty($extensions)) {
            $extensions = ['php'];
        }

        // Add common PHP exclusions
        $defaultExcludes = [
            '_ide_helper.php',
            '_ide_helper_models.php',
            '.phpstorm.meta.php',
        ];

        return parent::scan($path, $extensions, array_merge($excludePaths, $defaultExcludes));
    }
}
