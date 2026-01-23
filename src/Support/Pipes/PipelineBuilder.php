<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\Pipes;

use Closure;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\Sin;
use JesseGall\CodeCommandments\Results\Warning;
use JesseGall\CodeCommandments\Support\Pipes\Php\PhpContext;

/**
 * A fluent builder for creating pipelines from pipe classes.
 *
 * Example usage:
 *
 * PipelineBuilder::make(['filePath' => $filePath, 'content' => $content])
 *     ->pipe(ParsePhpAst::class)
 *     ->pipe(FilterLaravelControllers::class)
 *     ->returnRighteousWhen(fn($data) => empty($data['classes']))
 *     ->pipe(FindMethodDependencies::class)
 *     ->rejectUsing(fn($dep) => $this->isAllowedType($dep))
 *     ->mapToSins(fn($dep) => $this->createSin($dep))
 *     ->judge();
 */
final class PipelineBuilder
{
    private mixed $data;

    /** @var array<Sin> */
    private array $sins = [];

    /** @var array<Warning> */
    private array $warnings = [];

    private ?string $skipReason = null;

    private ?Judgment $earlyReturn = null;

    private function __construct(mixed $data)
    {
        $this->data = $data;

        // Automatically skip package files (Prophets/, Commandments/Validators/)
        // which contain example code in heredocs that should not be flagged
        if ($data instanceof PhpContext && $data->isPackageFile()) {
            $this->earlyReturn = Judgment::righteous();
        }
    }

    /**
     * Start a new pipeline with initial data.
     */
    public static function make(mixed $data): self
    {
        return new self($data);
    }

    /**
     * Pipe data through a pipe class or closure.
     *
     * @param  class-string<Pipe>|Pipe|Closure  $pipe
     */
    public function pipe(string|Pipe|Closure $pipe): self
    {
        if ($this->shouldSkip()) {
            return $this;
        }

        if (is_string($pipe)) {
            $pipe = new $pipe();
        }

        if ($pipe instanceof Pipe) {
            $this->data = $pipe->handle($this->data);
        } elseif ($pipe instanceof Closure) {
            $this->data = $pipe($this->data);
        }

        return $this;
    }

    /**
     * Apply multiple pipes in sequence.
     *
     * @param  array<class-string<Pipe>|Pipe|Closure>  $pipes
     */
    public function through(array $pipes): self
    {
        foreach ($pipes as $pipe) {
            $this->pipe($pipe);
        }

        return $this;
    }

    /**
     * Filter data using a callback.
     */
    public function filter(Closure $callback): self
    {
        if ($this->shouldSkip()) {
            return $this;
        }

        if (is_array($this->data)) {
            $this->data = array_values(array_filter($this->data, $callback));
        }

        return $this;
    }

    /**
     * Reject data using a callback.
     */
    public function reject(Closure $callback): self
    {
        return $this->filter(fn ($item) => ! $callback($item));
    }

    /**
     * Map data using a callback.
     */
    public function map(Closure $callback): self
    {
        if ($this->shouldSkip()) {
            return $this;
        }

        if (is_array($this->data)) {
            $this->data = array_map($callback, $this->data, array_keys($this->data));
        }

        return $this;
    }

    /**
     * Flat map data using a callback.
     */
    public function flatMap(Closure $callback): self
    {
        if ($this->shouldSkip()) {
            return $this;
        }

        if (is_array($this->data)) {
            $this->data = array_merge(...array_map($callback, $this->data));
        }

        return $this;
    }

    /**
     * Skip if a condition is true.
     *
     * @param  bool|Closure(mixed): bool  $condition
     */
    public function skipWhen(bool|Closure $condition, string $reason): self
    {
        if ($this->shouldSkip()) {
            return $this;
        }

        $shouldSkip = $condition instanceof Closure
            ? $condition($this->data)
            : $condition;

        if ($shouldSkip) {
            $this->skipReason = $reason;
        }

        return $this;
    }

    /**
     * Skip if data is empty or null.
     */
    public function skipIfEmpty(string $reason): self
    {
        if (empty($this->data)) {
            $this->skipReason = $reason;
        }

        return $this;
    }

    /**
     * Conditionally execute a callback.
     */
    public function when(bool $condition, Closure $callback): self
    {
        if ($condition && ! $this->shouldSkip()) {
            $callback($this);
        }

        return $this;
    }

    /**
     * Map current data to sins using a callback.
     *
     * If the data is an array, the callback receives each item and should return a Sin or null.
     * If the data is an object (like PhpContext), the callback receives the object and should
     * return an array of Sins or a single Sin.
     *
     * @param  Closure(mixed, int): (Sin|array<Sin>|null)  $callback
     */
    public function mapToSins(Closure $callback): self
    {
        if ($this->shouldSkip()) {
            return $this;
        }

        if (is_array($this->data)) {
            foreach ($this->data as $index => $item) {
                $result = $callback($item, $index);
                $this->collectSins($result);
            }
        } else {
            // For context objects, call the callback once with the whole object
            $result = $callback($this->data, 0);
            $this->collectSins($result);
        }

        return $this;
    }

    /**
     * Collect sins from a callback result.
     *
     * @param  Sin|array<Sin>|null  $result
     */
    private function collectSins(mixed $result): void
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
     * Create sins from context matches with the given message and suggestion.
     *
     * This is a convenience method for the common pattern of mapping matches to sins.
     * Message and suggestion can be strings or closures that receive the match.
     *
     * Example usage:
     *   ->sinsFromMatches('Using forbidden method', 'Use alternative instead')
     *   ->sinsFromMatches(fn ($m) => "Found {$m['name']}", 'Fix it')
     *
     * @param  string|Closure(array): string  $message
     * @param  string|Closure(array): string|null  $suggestion
     */
    public function sinsFromMatches(string|Closure $message, string|Closure|null $suggestion = null): self
    {
        if ($this->shouldSkip()) {
            return $this;
        }

        if (! $this->data instanceof PhpContext) {
            return $this;
        }

        foreach ($this->data->matches as $match) {
            $msg = $message instanceof Closure ? $message($match) : $message;
            $sug = $suggestion instanceof Closure ? $suggestion($match) : $suggestion;

            $this->sins[] = Sin::at(
                line: $match['line'],
                message: $msg,
                snippet: $match['content'] ?? null,
                suggestion: $sug,
            );
        }

        return $this;
    }

    /**
     * Map current data to warnings using a callback.
     *
     * @param  Closure(mixed, int): (Warning|array<Warning>|null)  $callback
     */
    public function mapToWarnings(Closure $callback): self
    {
        if ($this->shouldSkip()) {
            return $this;
        }

        if (is_array($this->data)) {
            foreach ($this->data as $index => $item) {
                $result = $callback($item, $index);
                $this->collectWarnings($result);
            }
        } else {
            $result = $callback($this->data, 0);
            $this->collectWarnings($result);
        }

        return $this;
    }

    /**
     * Collect warnings from a callback result.
     *
     * @param  Warning|array<Warning>|null  $result
     */
    private function collectWarnings(mixed $result): void
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
     * Add sins directly.
     *
     * @param  array<Sin>  $sins
     */
    public function addSins(array $sins): self
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
     * Return a specific judgment early if condition is true.
     *
     * @param  bool|Closure(mixed): bool  $condition
     */
    public function returnWhen(bool|Closure $condition, Judgment $judgment): self
    {
        if ($this->shouldSkip()) {
            return $this;
        }

        $shouldReturn = $condition instanceof Closure
            ? $condition($this->data)
            : $condition;

        if ($shouldReturn) {
            $this->earlyReturn = $judgment;
        }

        return $this;
    }

    /**
     * Return righteous judgment early if condition is true.
     *
     * @param  bool|Closure(mixed): bool  $condition
     */
    public function returnRighteousWhen(bool|Closure $condition): self
    {
        return $this->returnWhen($condition, Judgment::righteous());
    }

    /**
     * Return righteous judgment early if data (or a key) is empty.
     */
    public function returnRighteousIfEmpty(?string $key = null): self
    {
        return $this->returnRighteousWhen(function ($data) use ($key) {
            if ($key !== null) {
                return empty($data[$key] ?? null);
            }

            return empty($data);
        });
    }

    /**
     * Return righteous judgment early if context has no classes.
     */
    public function returnRighteousIfNoClasses(): self
    {
        return $this->returnRighteousWhen(fn ($ctx) => ! $ctx->hasClasses());
    }

    /**
     * Return righteous judgment early if context has no AST or classes.
     */
    public function returnRighteousIfNoAstOrClasses(): self
    {
        return $this->returnRighteousWhen(fn ($ctx) => ! $ctx->hasAst() || ! $ctx->hasClasses());
    }

    /**
     * Get the current data.
     */
    public function getData(): mixed
    {
        return $this->data;
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
}
