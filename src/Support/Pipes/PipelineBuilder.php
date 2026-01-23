<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Pipes;

use Closure;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\Sin;
use JesseGall\CodeCommandments\Results\Warning;
use JesseGall\CodeCommandments\Support\Pipes\MatchResult;

/**
 * Abstract base class for fluent pipeline builders.
 *
 * Provides common pipeline functionality for PHP and Vue analysis pipelines.
 *
 * @template TContext of object
 */
abstract class PipelineBuilder
{
    /** @var TContext */
    protected object $context;

    /** @var array<Sin> */
    protected array $sins = [];

    /** @var array<Warning> */
    protected array $warnings = [];

    protected ?string $skipReason = null;

    protected ?Judgment $earlyReturn = null;

    /**
     * Pipe context through a pipe class or closure.
     *
     * @param  class-string<Pipe>|Pipe|Closure  $pipe
     * @return static
     */
    public function pipe(string|Pipe|Closure $pipe): static
    {
        if ($this->shouldSkip()) {
            return $this;
        }

        if (is_string($pipe)) {
            $pipe = new $pipe();
        }

        if ($pipe instanceof Pipe) {
            $this->context = $pipe->handle($this->context);
        } elseif ($pipe instanceof Closure) {
            $this->context = $pipe($this->context);
        }

        return $this;
    }

    /**
     * Apply multiple pipes in sequence.
     *
     * @param  array<class-string<Pipe>|Pipe|Closure>  $pipes
     * @return static
     */
    public function through(array $pipes): static
    {
        foreach ($pipes as $pipe) {
            $this->pipe($pipe);
        }

        return $this;
    }

    /**
     * Return righteous judgment early if condition is true.
     *
     * @param  bool|Closure(TContext): bool  $condition
     * @return static
     */
    public function returnRighteousWhen(bool|Closure $condition): static
    {
        if ($this->shouldSkip()) {
            return $this;
        }

        $shouldReturn = $condition instanceof Closure
            ? $condition($this->context)
            : $condition;

        if ($shouldReturn) {
            $this->earlyReturn = Judgment::righteous();
        }

        return $this;
    }

    /**
     * Filter matches using a callback.
     *
     * @return static
     */
    public function filter(Closure $callback): static
    {
        if ($this->shouldSkip()) {
            return $this;
        }

        $this->context = $this->contextWithMatches(
            array_values(array_filter($this->getMatches(), $callback))
        );

        return $this;
    }

    /**
     * Reject matches using a callback (inverse of filter).
     *
     * @return static
     */
    public function reject(Closure $callback): static
    {
        return $this->filter(fn ($match) => ! $callback($match));
    }

    /**
     * Map context to sins using a callback.
     *
     * @param  Closure(TContext): (Sin|array<Sin>|null)  $callback
     * @return static
     */
    public function mapToSins(Closure $callback): static
    {
        if ($this->shouldSkip()) {
            return $this;
        }

        $result = $callback($this->context);
        $this->collectSins($result);

        return $this;
    }

    /**
     * Create sins from context matches with the given message and suggestion.
     *
     * @param  string|Closure(MatchResult): string  $message
     * @param  string|Closure(MatchResult): string|null  $suggestion
     * @return static
     */
    public function sinsFromMatches(string|Closure $message, string|Closure|null $suggestion = null): static
    {
        if ($this->shouldSkip()) {
            return $this;
        }

        foreach ($this->getMatches() as $match) {
            $msg = $message instanceof Closure ? $message($match) : $message;
            $sug = $suggestion instanceof Closure ? $suggestion($match) : $suggestion;

            $this->sins[] = Sin::at(
                line: $match->line,
                message: $msg,
                snippet: $match->content,
                suggestion: $sug,
            );
        }

        return $this;
    }

    /**
     * Create sins from each match using a callback.
     *
     * @param  Closure(MatchResult, static, int): ?Sin  $callback
     * @return static
     */
    public function forEachMatch(Closure $callback): static
    {
        if ($this->shouldSkip()) {
            return $this;
        }

        foreach ($this->getMatches() as $index => $match) {
            $sin = $callback($match, $this, $index);

            if ($sin instanceof Sin) {
                $this->sins[] = $sin;
            }
        }

        return $this;
    }

    /**
     * Map context to warnings using a callback.
     *
     * @param  Closure(TContext): (Warning|array<Warning>|null)  $callback
     * @return static
     */
    public function mapToWarnings(Closure $callback): static
    {
        if ($this->shouldSkip()) {
            return $this;
        }

        $result = $callback($this->context);
        $this->collectWarnings($result);

        return $this;
    }

    /**
     * Add sins directly.
     *
     * @param  array<Sin>  $sins
     * @return static
     */
    public function addSins(array $sins): static
    {
        $this->sins = array_merge($this->sins, $sins);

        return $this;
    }

    /**
     * Check if pipeline should skip processing.
     */
    public function shouldSkip(): bool
    {
        return $this->skipReason !== null || $this->earlyReturn !== null;
    }

    /**
     * Set a skip reason.
     *
     * @return static
     */
    public function skip(string $reason): static
    {
        $this->skipReason = $reason;

        return $this;
    }

    /**
     * Get the current context.
     *
     * @return TContext
     */
    public function getContext(): object
    {
        return $this->context;
    }

    /**
     * Get the accumulated sins.
     *
     * @return array<Sin>
     */
    public function getSins(): array
    {
        return $this->sins;
    }

    /**
     * Get the accumulated warnings.
     *
     * @return array<Warning>
     */
    public function getWarnings(): array
    {
        return $this->warnings;
    }

    /**
     * Build and return the final Judgment.
     */
    public function judge(): Judgment
    {
        if ($this->earlyReturn !== null) {
            return $this->earlyReturn;
        }

        if ($this->skipReason !== null) {
            return Judgment::skipped($this->skipReason);
        }

        if (! empty($this->sins)) {
            return Judgment::fallen($this->sins);
        }

        if (! empty($this->warnings)) {
            return Judgment::withWarnings($this->warnings);
        }

        return Judgment::righteous();
    }

    /**
     * Get matches from the context.
     *
     * @return array<MatchResult>
     */
    abstract protected function getMatches(): array;

    /**
     * Create a new context with updated matches.
     *
     * @param  array  $matches
     * @return TContext
     */
    abstract protected function contextWithMatches(array $matches): object;

    /**
     * Collect sins from a callback result.
     */
    protected function collectSins(mixed $result): void
    {
        if ($result instanceof Sin) {
            $this->sins[] = $result;
        } elseif (is_array($result)) {
            foreach ($result as $sin) {
                if ($sin instanceof Sin) {
                    $this->sins[] = $sin;
                }
            }
        }
    }

    /**
     * Collect warnings from a callback result.
     */
    protected function collectWarnings(mixed $result): void
    {
        if ($result instanceof Warning) {
            $this->warnings[] = $result;
        } elseif (is_array($result)) {
            foreach ($result as $warning) {
                if ($warning instanceof Warning) {
                    $this->warnings[] = $warning;
                }
            }
        }
    }
}
