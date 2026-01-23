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
 *     ->pipe(ParsePhpAst::class)
 *     ->pipe(ExtractClasses::class)
 *     ->pipe(FilterLaravelControllers::class)
 *     ->returnRighteousIfNoClasses()
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
     * Return righteous judgment early if context has no classes.
     */
    public function returnRighteousIfNoClasses(): self
    {
        return $this->returnRighteousWhen(fn (PhpContext $ctx) => ! $ctx->hasClasses());
    }

    /**
     * Return righteous judgment early if context has no AST or classes.
     */
    public function returnRighteousIfNoAstOrClasses(): self
    {
        return $this->returnRighteousWhen(fn (PhpContext $ctx) => ! $ctx->hasAst() || ! $ctx->hasClasses());
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
