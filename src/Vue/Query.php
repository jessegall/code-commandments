<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue;

use Closure;

/**
 * The frontend twin of the backend {@see \JesseGall\CodeCommandments\Ast\Query}:
 * the SAME fluent pattern — a selector opens it, `where`/`reject` narrow it (one
 * check per line, ANDed), terminals return rich {@see ElementMatch}es that know
 * their `file:line`. A frontend detector reads exactly like a backend one; only
 * the nodes differ (Vue {@see Element}s instead of PHP).
 *
 * The selector sees the raw {@see Element}; `where`/`reject` see the {@see
 * ElementMatch} (which IS an Element), mirroring the backend.
 */
final class Query
{
    /** @var list<Closure(ElementMatch): bool> */
    private array $filters = [];

    /**
     * @param  Closure(Element): bool  $selector
     */
    public function __construct(
        private readonly Codebase $codebase,
        private readonly Closure $selector,
    ) {}

    /**
     * @param  Closure(ElementMatch): bool  $check
     */
    public function where(Closure $check): self
    {
        $this->filters[] = $check;

        return $this;
    }

    /**
     * @param  Closure(ElementMatch): bool  $check
     */
    public function reject(Closure $check): self
    {
        $this->filters[] = static fn (ElementMatch $match): bool => ! $check($match);

        return $this;
    }

    /**
     * Keep elements carrying the given Vue directive (`Directive::If`, or its name).
     * A non-directive string throws — use {@see where} with `hasAttribute()` for an
     * arbitrary bound attribute like `:title`.
     */
    public function withDirective(string|Directive $name): self
    {
        $directive = Directive::name($name);

        $this->filters[] = static fn (ElementMatch $match): bool => $match->hasAttribute($directive);

        return $this;
    }

    /**
     * Keep elements whose subtree is at least $elements elements big — the floor
     * that separates a substantial block from a trivial look-alike.
     */
    public function ofAtLeastSize(int $elements): self
    {
        $this->filters[] = static fn (ElementMatch $match): bool => $match->subtreeSize() >= $elements;

        return $this;
    }

    /**
     * Keep elements whose component's `<template>` spans at least $lines — the
     * "big enough that extracting is worth it" gate.
     */
    public function inTemplateOfAtLeast(int $lines): self
    {
        $this->filters[] = static fn (ElementMatch $match): bool => $match->sfc->templateLineCount() >= $lines;

        return $this;
    }

    /**
     * Keep elements that bind or interpolate a data chain reaching at least $depth
     * property hops past its root (`data.user.firstName` is depth 2). $ignoring names
     * pass-through accessors (`value`, `length`) that don't deepen the reach.
     *
     * @param  list<string>  $ignoring
     */
    public function reachesAtLeast(int $depth, array $ignoring = []): self
    {
        $this->filters[] = static function (ElementMatch $match) use ($depth, $ignoring): bool {
            foreach ($match->expressions() as $expression) {
                if ($expression->memberDepth($ignoring) >= $depth) {
                    return true;
                }
            }

            return false;
        };

        return $this;
    }

    /**
     * @return list<ElementMatch>
     */
    public function get(): array
    {
        $matches = [];

        foreach ($this->codebase->nodes() as [$element, $sfc]) {
            if (! ($this->selector)($element)) {
                continue;
            }

            $match = new ElementMatch($element, $sfc);

            foreach ($this->filters as $filter) {
                if (! $filter($match)) {
                    continue 2;
                }
            }

            $matches[] = $match;
        }

        return $matches;
    }

    public function first(): ?ElementMatch
    {
        return $this->get()[0] ?? null;
    }

    public function count(): int
    {
        return count($this->get());
    }
}
