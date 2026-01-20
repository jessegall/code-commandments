<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Results\Judgment;

/**
 * Commandment: All Data classes must have #[TypeScript] attribute.
 */
class TypeScriptAttributeProphet extends PhpCommandment
{
    protected array $utilityPaths = [
        'Data/Casts/',
        'Data/Attributes/',
        'Data/Transformers/',
        'Data/Concerns/',
        'Data/Contracts/',
        'Data/Pipes/',
        'Data/Common/BooleanCast',
        'Data/Common/QuantifiedItemCollection',
    ];

    public function description(): string
    {
        return 'Add #[TypeScript] attribute to all Data classes';
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
All Data classes must have the #[TypeScript] attribute.

This ensures TypeScript types are automatically generated for all DTOs,
providing type safety between backend and frontend.

Bad:
    class ProductData extends Data {
        public function __construct(
            public readonly string $name,
        ) {}
    }

Good:
    #[TypeScript]
    class ProductData extends Data {
        public function __construct(
            public readonly string $name,
        ) {}
    }

Run `php artisan typescript:transform` to regenerate types after changes.
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        // Only check Data classes and Page Data classes
        $isDataFile = str_contains($filePath, '/Data/') || str_contains($filePath, '\\Data\\');
        $isViewFile = str_contains($filePath, 'Http/View') || str_contains($filePath, 'Http\\View');

        if (!$isDataFile && !$isViewFile) {
            return $this->righteous();
        }

        // Skip utility classes
        if ($this->isUtilityClass($filePath)) {
            return $this->righteous();
        }

        // For View files, only check files ending with Page.php or Data.php
        if ($isViewFile && !preg_match('/(Page|Data)\.php$/', $filePath)) {
            return $this->righteous();
        }

        // Skip traits (they can't have attributes applied)
        if (preg_match('/^trait\s+\w+/m', $content)) {
            return $this->righteous();
        }

        // Skip classes that don't extend Data
        if (!preg_match('/extends\s+.*Data/', $content) && !preg_match('/extends\s+Data\b/', $content)) {
            return $this->righteous();
        }

        // Check for #[TypeScript] attribute
        if (preg_match('/#\[TypeScript\]/', $content)) {
            return $this->righteous();
        }

        // Extract class name for the error message
        $className = basename($filePath, '.php');

        return $this->fallen([
            $this->sinAt(
                1,
                "Data class '{$className}' missing #[TypeScript] attribute",
                null,
                'Add #[TypeScript] attribute to enable TypeScript generation'
            ),
        ]);
    }

    private function isUtilityClass(string $filePath): bool
    {
        foreach ($this->utilityPaths as $path) {
            if (str_contains($filePath, $path)) {
                return true;
            }
        }

        return false;
    }
}
