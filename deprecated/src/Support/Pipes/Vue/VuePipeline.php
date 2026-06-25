<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Pipes\Vue;

use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\Sin;
use JesseGall\CodeCommandments\Support\Pipes\MatchResult;
use JesseGall\CodeCommandments\Support\Pipes\PipelineBuilder;
use JesseGall\CodeCommandments\Support\RegexMatcher;

/**
 * A fluent pipeline for Vue SFC analysis.
 *
 * Example usage:
 *
 * VuePipeline::make($filePath, $content)
 *     ->inTemplate()
 *     ->matchAll('/pattern/')
 *     ->sinsFromMatches('Message', 'Suggestion')
 *     ->judge();
 *
 * @extends PipelineBuilder<VueContext>
 */
final class VuePipeline extends PipelineBuilder
{
    private function __construct(VueContext $context)
    {
        $this->context = $context;

        // Automatically skip package files (Prophets/, Commandments/Validators/)
        if ($context->isPackageFile()) {
            $this->earlyReturn = Judgment::righteous();
        }
    }

    /**
     * Start a new Vue pipeline.
     */
    public static function make(string $filePath, string $content): self
    {
        return new self(VueContext::from($filePath, $content));
    }

    /**
     * Extract the template section and return righteous if not found.
     *
     * Combines: extractTemplate + returnRighteousIfNoTemplate
     */
    public function inTemplate(): self
    {
        return $this
            ->extractTemplate()
            ->returnRighteousIfNoTemplate();
    }

    /**
     * Extract the script section and return righteous if not found.
     *
     * Combines: extractScript + returnRighteousIfNoScript
     */
    public function inScript(): self
    {
        return $this
            ->extractScript()
            ->returnRighteousIfNoScript();
    }

    /**
     * Return righteous judgment early if context has no template section.
     */
    public function returnRighteousIfNoTemplate(): self
    {
        return $this->returnRighteousWhen(fn (VueContext $ctx) => ! $ctx->hasTemplate());
    }

    /**
     * Extract the template section from the Vue SFC.
     */
    public function extractTemplate(): self
    {
        return $this->pipe(ExtractTemplate::class);
    }

    /**
     * Return righteous judgment early if context has no script section.
     */
    public function returnRighteousIfNoScript(): self
    {
        return $this->returnRighteousWhen(fn (VueContext $ctx) => ! $ctx->hasScript());
    }

    /**
     * Extract the script section from the Vue SFC.
     */
    public function extractScript(): self
    {
        return $this->pipe(ExtractScript::class);
    }

    /**
     * Skip with reason if context has no template section.
     */
    public function skipIfNoTemplate(string $reason = 'No template section found'): self
    {
        if (! $this->shouldSkip() && ! $this->context->hasTemplate()) {
            $this->skipReason = $reason;
        }

        return $this;
    }

    /**
     * Skip with reason if context has no script section.
     */
    public function skipIfNoScript(string $reason = 'No script section found'): self
    {
        if (! $this->shouldSkip() && ! $this->context->hasScript()) {
            $this->skipReason = $reason;
        }

        return $this;
    }

    /**
     * Return righteous if file is not a page file.
     */
    public function onlyPageFiles(): self
    {
        return $this->returnRighteousWhen(fn (VueContext $ctx) =>
            ! $ctx->filePathContains('/Pages/') && ! $ctx->filePathContains('/pages/')
        );
    }

    /**
     * Return righteous if file is not a component file.
     */
    public function onlyComponentFiles(): self
    {
        return $this->returnRighteousWhen(fn (VueContext $ctx) =>
            ! $ctx->filePathContains('/Components/') && ! $ctx->filePathContains('/components/')
        );
    }

    /**
     * Return righteous if file is a partial file.
     */
    public function excludePartialFiles(): self
    {
        return $this->returnRighteousWhen(fn (VueContext $ctx) =>
            $ctx->filePathContains('/Partials/') || $ctx->filePathContains('/partials/')
        );
    }

    /**
     * Return righteous if content matches the given pattern.
     */
    public function returnRighteousIfContentMatches(string $pattern): self
    {
        return $this->returnRighteousWhen(fn (VueContext $ctx) =>
            (bool) preg_match($pattern, $ctx->content)
        );
    }

    /**
     * Return righteous if section content matches the given pattern.
     *
     * @param  'template'|'script'|null  $section  Which section to check (null = auto-detect)
     */
    public function returnRighteousIfSectionMatches(string $pattern, ?string $section = null): self
    {
        return $this->returnRighteousWhen(function (VueContext $ctx) use ($pattern, $section) {
            $content = match ($section) {
                'template' => $ctx->template['content'] ?? null,
                'script' => $ctx->script['content'] ?? null,
                default => $ctx->getSectionContent(),
            };

            return $content !== null && (bool) preg_match($pattern, $content);
        });
    }

    /**
     * Match all occurrences of a pattern in the current section.
     */
    public function matchAll(string $pattern): self
    {
        if ($this->shouldSkip()) {
            return $this;
        }

        $sectionContent = $this->context->getSectionContent();

        if ($sectionContent === null) {
            return $this;
        }

        $rawMatches = RegexMatcher::for($sectionContent)->matchAll($pattern);

        // Convert to Match objects with line numbers
        $this->context = $this->context->with(
            matches: array_map(fn ($match) => new MatchResult(
                name: 'matchAll',
                pattern: $pattern,
                match: $match['match'],
                line: $this->context->getLineFromOffset($match['offset']),
                offset: $match['offset'],
                content: trim($match['match']),
                groups: $match['groups'],
            ), $rawMatches)
        );

        return $this;
    }

    /**
     * Get the line number for an offset in the current section.
     */
    public function getLineFromOffset(int $offset): int
    {
        return $this->context->getLineFromOffset($offset);
    }

    /**
     * Create a sin at a specific offset in the section.
     */
    public function sinAt(int $offset, string $message, ?string $snippet = null, ?string $suggestion = null): Sin
    {
        return Sin::at($this->getLineFromOffset($offset), $message, $snippet, $suggestion);
    }

    /**
     * Get a snippet of the section content at an offset.
     */
    public function getSnippet(int $offset, int $length = 60): string
    {
        return $this->context->getSnippet($offset, $length);
    }

    /**
     * Get the section content.
     */
    public function getSectionContent(): ?string
    {
        return $this->context->getSectionContent();
    }

    /**
     * Get the section start offset.
     */
    public function getSectionStart(): int
    {
        return $this->context->getSectionStart();
    }

    /**
     * Get the original content.
     */
    public function getContent(): string
    {
        return $this->context->content;
    }

    /**
     * Get the file path.
     */
    public function getFilePath(): string
    {
        return $this->context->filePath;
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
    protected function contextWithMatches(array $matches): VueContext
    {
        return $this->context->with(matches: $matches);
    }
}
