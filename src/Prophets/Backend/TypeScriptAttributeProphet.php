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
        // Skip utility classes
        if ($this->isUtilityClass($filePath)) {
            return $this->righteous();
        }

        // Only check Data classes (using AST)
        $ast = $this->parse($content);

        if (!$ast || !$this->isLaravelClass($ast, 'data')) {
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
