<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Pipes\Php;

use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\Sin;
use JesseGall\CodeCommandments\Support\Pipes\PipelineBuilder;

/**
 * A fluent pipeline for PHP file analysis.
 *
 * Example usage:
 *
 * PhpPipeline::make($filePath, $content)
 *     ->onlyControllers()
 *     ->pipe(new MatchPatterns()->add('pattern', '/regex/'))
 *     ->sinsFromMatches('Message', 'Suggestion')
 *     ->judge();
 *
 * @extends PipelineBuilder<PhpContext>
 */
final class PhpPipeline extends PipelineBuilder
{
    private function __construct(PhpContext $context)
    {
        $this->context = $context;

        // Automatically skip package files (Prophets/, Commandments/Validators/)
        if ($context->isPackageFile()) {
            $this->earlyReturn = Judgment::righteous();
        }
    }

    /**
     * Start a new PHP pipeline.
     */
    public static function make(string $filePath, string $content): self
    {
        return new self(PhpContext::from($filePath, $content));
    }

    /**
     * Filter to only Laravel controllers and return righteous if none found.
     *
     * Combines: ParsePhpAst -> ExtractClass -> FilterLaravelController -> returnRighteousIfNoClass
     */
    public function onlyControllers(): self
    {
        return $this
            ->pipe(ParsePhpAst::class)
            ->pipe(ExtractClass::class)
            ->pipe(FilterLaravelController::class)
            ->returnRighteousIfNoClass();
    }

    /**
     * Filter to only Laravel Data classes and return righteous if none found.
     *
     * Combines: ParsePhpAst -> ExtractUseStatements -> ExtractClass -> FilterLaravelDataClass -> returnRighteousIfNoClass
     */
    public function onlyDataClasses(): self
    {
        return $this
            ->pipe(ParsePhpAst::class)
            ->pipe(ExtractUseStatements::class)
            ->pipe(ExtractClass::class)
            ->pipe(FilterLaravelDataClass::class)
            ->returnRighteousIfNoClass();
    }

    /**
     * Filter to only FormRequest classes and return righteous if none found.
     *
     * Combines: ParsePhpAst -> ExtractClass -> FilterFormRequestClass -> returnRighteousIfNoClass
     */
    public function onlyFormRequestClasses(): self
    {
        return $this
            ->pipe(ParsePhpAst::class)
            ->pipe(ExtractClass::class)
            ->pipe(FilterFormRequestClass::class)
            ->returnRighteousIfNoClass();
    }

    /**
     * Return righteous judgment early if context has no classes.
     */
    public function returnRighteousIfNoClass(): self
    {
        return $this->returnRighteousWhen(fn (PhpContext $ctx) => ! $ctx->hasClasses());
    }

    /**
     * Return righteous judgment early if context has no AST or classes.
     */
    public function returnRighteousIfNoAstOrClass(): self
    {
        return $this->returnRighteousWhen(fn (PhpContext $ctx) => ! $ctx->hasAst() || ! $ctx->hasClasses());
    }

    /**
     * Return righteous judgment early if any class has the specified attribute.
     *
     * Uses AST analysis to check for attributes without requiring class loading.
     *
     * @param  string  $attributeName  The short or fully qualified attribute name (e.g., 'TypeScript' or 'Spatie\TypeScriptTransformer\Attributes\TypeScript')
     */
    public function returnRighteousWhenClassHasAttribute(string $attributeName): self
    {
        return $this->returnRighteousWhen(fn (PhpContext $ctx) => $this->classHasAttribute($ctx, $attributeName));
    }

    /**
     * Check if any class in the context has the specified attribute.
     */
    private function classHasAttribute(PhpContext $ctx, string $attributeName): bool
    {
        // Get just the short name for comparison
        $shortName = class_basename($attributeName);

        foreach ($ctx->classes as $class) {
            foreach ($class->attrGroups as $attrGroup) {
                foreach ($attrGroup->attrs as $attr) {
                    $attrName = $attr->name->toString();

                    // Match against full name or short name
                    if ($attrName === $attributeName || $attrName === $shortName || class_basename($attrName) === $shortName) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Create a sin at a specific line.
     */
    public function sinAt(int $line, string $message, ?string $snippet = null, ?string $suggestion = null): Sin
    {
        return Sin::at($line, $message, $snippet, $suggestion);
    }

    /**
     * Get matches from the context.
     */
    protected function getMatches(): array
    {
        return $this->context->matches;
    }

    /**
     * Create a new context with updated matches.
     */
    protected function contextWithMatches(array $matches): PhpContext
    {
        return $this->context->with(matches: $matches);
    }
}
