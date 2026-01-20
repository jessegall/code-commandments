<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Scanners;

/**
 * Scanner specialized for Vue/TypeScript/JavaScript files.
 */
class VueFileScanner extends GenericFileScanner
{
    public function scan(string $path, array $extensions = [], array $excludePaths = []): iterable
    {
        // Default to frontend file extensions if none specified
        if (empty($extensions)) {
            $extensions = ['vue', 'ts', 'js', 'tsx', 'jsx'];
        }

        // Add common frontend exclusions
        $defaultExcludes = [
            'dist/',
            'build/',
            '.nuxt/',
            '.output/',
            'coverage/',
            '*.min.js',
            '*.bundle.js',
            'shims-vue.d.ts',
        ];

        return parent::scan($path, $extensions, array_merge($excludePaths, $defaultExcludes));
    }
}
