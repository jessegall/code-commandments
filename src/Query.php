<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments;

use Closure;
use ReflectionFunction;
use ReflectionNamedType;

/**
 * The fluent query, shared by both engines — the ONE place `where`/`reject`, the filter loop, and
 * the reflection-driven decorator injection live, so the backend ({@see Ast\Query}) and frontend
 * ({@see Vue\Query}) never write that machinery twice. A subclass supplies only the three things
 * that genuinely differ per engine: where the candidate nodes come from ({@see selected}), how a
 * candidate becomes a match ({@see wrap}), and the base match class its decorators extend
 * ({@see matchClass}). Everything else — including "a `where` closure that type-hints a decorator
 * gets its match re-wrapped in that class" — is engine-agnostic and lives here.
 */
abstract class Query
{
    /**
     * Each filter paired with the decorator its check type-hinted (or null for the base match).
     *
     * @var list<array{0: Closure, 1: class-string|null}>
     */
    protected array $filters = [];

    /**
     * Keep matches passing the check. A check that type-hints a decorator ({@see matchClass}
     * subclass) is handed that decorated match; a plain one the base match.
     */
    public function where(Closure $check): static
    {
        $this->filters[] = [$check, $this->decoratorOf($check)];

        return $this;
    }

    /**
     * Keep matches that do NOT pass the check — the inverse of {@see where}.
     */
    public function reject(Closure $check): static
    {
        $this->filters[] = [static fn (object $match): bool => ! $check($match), $this->decoratorOf($check)];

        return $this;
    }

    /**
     * Add an engine selector's own pre-typed filter (`withDirective`, `isUsedOn`, …) — no decorator
     * reflection, it already knows the type it reads.
     */
    protected function filter(Closure $check): static
    {
        $this->filters[] = [$check, null];

        return $this;
    }

    /**
     * @return list<object>  the matches (each a {@see matchClass} instance)
     */
    public function get(): array
    {
        $matches = [];

        foreach ($this->selected() as $candidate) {
            $match = $this->wrap($candidate, null);

            foreach ($this->filters as [$check, $as]) {
                $argument = $as === null ? $match : $this->wrap($candidate, $as);

                if (! $check($argument)) {
                    continue 2;
                }
            }

            $matches[] = $match;
        }

        return $matches;
    }

    /**
     * @return list<string>
     */
    public function locations(): array
    {
        return array_map(static fn (object $match): string => $match->location(), $this->get());
    }

    public function count(): int
    {
        return count($this->get());
    }

    public function first(): ?object
    {
        return $this->get()[0] ?? null;
    }

    /**
     * The candidates this query draws from — already narrowed by its selector. Each is an opaque
     * pair the subclass's {@see wrap} knows how to turn into a match.
     *
     * @return iterable<mixed>
     */
    abstract protected function selected(): iterable;

    /**
     * Turn one candidate into a match — the base {@see matchClass}, or the $as decorator when a
     * check asked for it.
     *
     * @param  class-string|null  $as
     */
    abstract protected function wrap(mixed $candidate, ?string $as): object;

    /**
     * The base match class a decorator must extend to be injected (`NodeMatch` / `ElementMatch`).
     *
     * @return class-string
     */
    abstract protected function matchClass(): string;

    /**
     * The decorator a check type-hinted its first parameter as — a {@see matchClass} subclass to
     * re-wrap the match in for that check. Null for the base type, or an untyped/builtin parameter.
     *
     * @return class-string|null
     */
    private function decoratorOf(Closure $check): ?string
    {
        $type = ((new ReflectionFunction($check))->getParameters()[0] ?? null)?->getType();

        if (! $type instanceof ReflectionNamedType || $type->isBuiltin()) {
            return null;
        }

        $class = $type->getName();
        $base = $this->matchClass();

        return is_a($class, $base, true) && $class !== $base ? $class : null;
    }
}
