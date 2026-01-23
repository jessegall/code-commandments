<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

use Closure;

/**
 * A fluent pipeline for processing data through a series of operations.
 *
 * @template T
 */
final class Pipeline
{
    /**
     * @param  T  $value
     * @param  array<Closure>  $pipes
     */
    private function __construct(
        private mixed $value,
        private array $pipes = [],
    ) {}

    /**
     * Start a new pipeline with the given value.
     *
     * @template TValue
     * @param  TValue  $value
     * @return self<TValue>
     */
    public static function from(mixed $value): self
    {
        return new self($value);
    }

    /**
     * Add a transformation pipe.
     *
     * @param  Closure(T): mixed  $callback
     * @return self<mixed>
     */
    public function pipe(Closure $callback): self
    {
        return new self($callback($this->value));
    }

    /**
     * Apply a callback if the condition is true.
     *
     * @param  bool|Closure(): bool  $condition
     * @param  Closure(T): mixed  $callback
     * @return self<T|mixed>
     */
    public function when(bool|Closure $condition, Closure $callback): self
    {
        $shouldApply = is_callable($condition) ? $condition() : $condition;

        return $shouldApply ? $this->pipe($callback) : $this;
    }

    /**
     * Filter items (for array values).
     *
     * @param  Closure(mixed, mixed): bool  $callback
     * @return self<array>
     */
    public function filter(Closure $callback): self
    {
        return new self(array_filter((array) $this->value, $callback, ARRAY_FILTER_USE_BOTH));
    }

    /**
     * Map over items (for array values).
     *
     * @param  Closure(mixed, mixed): mixed  $callback
     * @return self<array>
     */
    public function map(Closure $callback): self
    {
        $result = [];
        foreach ((array) $this->value as $key => $item) {
            $result[$key] = $callback($item, $key);
        }

        return new self($result);
    }

    /**
     * Flat map over items (for array values).
     *
     * @param  Closure(mixed, mixed): array  $callback
     * @return self<array>
     */
    public function flatMap(Closure $callback): self
    {
        $result = [];
        foreach ((array) $this->value as $key => $item) {
            $mapped = $callback($item, $key);
            foreach ((array) $mapped as $value) {
                $result[] = $value;
            }
        }

        return new self($result);
    }

    /**
     * Reject items that match the callback (inverse of filter).
     *
     * @param  Closure(mixed, mixed): bool  $callback
     * @return self<array>
     */
    public function reject(Closure $callback): self
    {
        return $this->filter(fn ($item, $key) => ! $callback($item, $key));
    }

    /**
     * Take only items that are not empty.
     *
     * @return self<array>
     */
    public function compact(): self
    {
        return $this->filter(fn ($item) => ! empty($item));
    }

    /**
     * Get unique values.
     *
     * @return self<array>
     */
    public function unique(): self
    {
        return new self(array_unique((array) $this->value));
    }

    /**
     * Get array values (reset keys).
     *
     * @return self<array>
     */
    public function values(): self
    {
        return new self(array_values((array) $this->value));
    }

    /**
     * Take the first N items.
     *
     * @return self<array>
     */
    public function take(int $count): self
    {
        return new self(array_slice((array) $this->value, 0, $count));
    }

    /**
     * Skip the first N items.
     *
     * @return self<array>
     */
    public function skip(int $count): self
    {
        return new self(array_slice((array) $this->value, $count));
    }

    /**
     * Execute a callback for each item without modifying the pipeline.
     *
     * @param  Closure(mixed, mixed): void  $callback
     * @return self<T>
     */
    public function each(Closure $callback): self
    {
        foreach ((array) $this->value as $key => $item) {
            $callback($item, $key);
        }

        return $this;
    }

    /**
     * Reduce the array to a single value.
     *
     * @template TReduce
     * @param  Closure(TReduce, mixed, mixed): TReduce  $callback
     * @param  TReduce  $initial
     * @return self<TReduce>
     */
    public function reduce(Closure $callback, mixed $initial = null): self
    {
        $result = $initial;
        foreach ((array) $this->value as $key => $item) {
            $result = $callback($result, $item, $key);
        }

        return new self($result);
    }

    /**
     * Get the final value.
     *
     * @return T
     */
    public function get(): mixed
    {
        return $this->value;
    }

    /**
     * Get the value as an array.
     *
     * @return array<mixed>
     */
    public function toArray(): array
    {
        return array_values((array) $this->value);
    }

    /**
     * Check if the result is empty.
     */
    public function isEmpty(): bool
    {
        return empty($this->value);
    }

    /**
     * Check if the result is not empty.
     */
    public function isNotEmpty(): bool
    {
        return ! $this->isEmpty();
    }

    /**
     * Get the count of items.
     */
    public function count(): int
    {
        return count((array) $this->value);
    }

    /**
     * Get the first item or null.
     */
    public function first(): mixed
    {
        $array = (array) $this->value;

        return reset($array) ?: null;
    }

    /**
     * Get the last item or null.
     */
    public function last(): mixed
    {
        $array = (array) $this->value;

        return end($array) ?: null;
    }
}
