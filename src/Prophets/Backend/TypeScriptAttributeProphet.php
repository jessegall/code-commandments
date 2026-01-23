<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Support\Pipes\Php\ExtractClasses;
use JesseGall\CodeCommandments\Support\Pipes\Php\FilterLaravelDataClasses;
use JesseGall\CodeCommandments\Support\Pipes\Php\ParsePhpAst;
use JesseGall\CodeCommandments\Support\Pipes\Php\PhpContext;
use JesseGall\CodeCommandments\Support\Pipes\Php\PhpPipeline;

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
        return PhpPipeline::make($filePath, $content)
            ->returnRighteousWhen(fn (PhpContext $ctx) => $this->isUtilityClass($ctx->filePath))
            ->pipe(ParsePhpAst::class)
            ->pipe(ExtractClasses::class)
            ->pipe(FilterLaravelDataClasses::class)
            ->returnRighteousIfNoClasses()
            ->returnRighteousWhen(fn (PhpContext $ctx) => (bool) preg_match('/#\[TypeScript\]/', $ctx->content))
            ->mapToSins(fn (PhpContext $ctx) => $this->sinAt(
                1,
                "Data class '".basename($ctx->filePath, '.php')."' missing #[TypeScript] attribute",
                null,
                'Add #[TypeScript] attribute to enable TypeScript generation'
            ))
            ->judge();
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
