<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue;

use Closure;
use JesseGall\CodeCommandments\Query as BaseQuery;

/**
 * The frontend fluent query over the Vue template AST — the SAME shared {@see BaseQuery}
 * (`where`/`reject`, the filter loop, decorator injection) the backend uses, plus the frontend
 * selectors and the three engine hooks: candidates are the [element, owning component] pairs the
 * query draws from, a match is an {@see ElementMatch}, and decorators extend it. A frontend
 * detector reads exactly like a backend one — including `->where(fn (MyElementNode $n) => …)` — only
 * the nodes differ (Vue {@see Element}s instead of PHP).
 *
 * The selector sees the raw {@see Element}; `where`/`reject` see the {@see ElementMatch}.
 */
final class Query extends BaseQuery
{
    /**
     * @param  Closure(): list<array{Element, Sfc}>  $nodes  the [element, owning component] pairs
     *         this query draws from — the whole codebase, or one component's subtree
     * @param  Closure(Element): bool  $selector
     */
    public function __construct(
        private readonly Closure $nodes,
        private readonly Closure $selector,
    ) {}

    /**
     * Keep elements carrying the given Vue directive (`Directive::If`, or its name). A
     * non-directive string throws — use {@see where} with `hasAttribute()` for an arbitrary bound
     * attribute like `:title`.
     */
    public function withDirective(string|Directive $name): self
    {
        $directive = Directive::name($name);

        return $this->filter(static fn (ElementMatch $match): bool => $match->hasAttribute($directive));
    }

    /**
     * Keep elements carrying a directive FAMILY — the directive or any of its arg / modifier
     * variants (`v-model`, `v-model:title`, `v-model.lazy`). Composes the node's own
     * {@see Element::directiveBindings} so the family match lives on the AST.
     */
    public function withDirectiveFamily(Directive $directive): self
    {
        return $this->filter(static fn (ElementMatch $match): bool => $match->directiveBindings($directive) !== []);
    }

    /**
     * Keep elements carrying ANY of the given directives.
     */
    public function withAnyDirective(Directive ...$directives): self
    {
        return $this->filter(static function (ElementMatch $match) use ($directives): bool {
            foreach ($directives as $directive) {
                if ($match->hasAttribute($directive)) {
                    return true;
                }
            }

            return false;
        });
    }

    /**
     * Drop elements of the given tag (case-insensitive) — e.g. exempt `<template>`.
     */
    public function rejectTag(string $tag): self
    {
        return $this->filter(static fn (ElementMatch $match): bool => strtolower($match->tag) !== strtolower($tag));
    }

    /**
     * Keep elements whose subtree is at least $elements elements big — the floor that separates a
     * substantial block from a trivial look-alike.
     */
    public function ofAtLeastSize(int $elements): self
    {
        return $this->filter(static fn (ElementMatch $match): bool => $match->subtreeSize() >= $elements);
    }

    /**
     * Keep elements whose component's `<template>` spans at least $lines — the "big enough that
     * extracting is worth it" gate.
     */
    public function inTemplateOfAtLeast(int $lines): self
    {
        return $this->filter(static fn (ElementMatch $match): bool => $match->sfc->templateLineCount() >= $lines);
    }

    /**
     * Keep elements that bind or interpolate a data chain reaching at least $depth property hops
     * past its root (`data.user.firstName` is depth 2). $ignoring names pass-through accessors
     * (`value`, `length`) that don't deepen the reach.
     *
     * @param  list<string>  $ignoring
     */
    public function reachesAtLeast(int $depth, array $ignoring = []): self
    {
        return $this->filter(static function (ElementMatch $match) use ($depth, $ignoring): bool {
            foreach ($match->expressions() as $expression) {
                if ($expression->memberDepth($ignoring) >= $depth) {
                    return true;
                }
            }

            return false;
        });
    }

    /**
     * Keep elements nested DEEPER than $depth levels that still have MORE than $remaining levels of
     * structure below them — a too-deep node burying substantial markup (so a deep but
     * shallow-below leaf isn't flagged).
     */
    public function nestedDeeperThan(int $depth, int $remaining): self
    {
        $this->filter(static fn (ElementMatch $match): bool => $match->depth() > $depth);

        return $this->filter(static fn (ElementMatch $match): bool => $match->height() - 1 > $remaining);
    }

    protected function selected(): iterable
    {
        foreach (($this->nodes)() as [$element, $sfc]) {
            if (($this->selector)($element)) {
                yield [$element, $sfc];
            }
        }
    }

    protected function wrap(mixed $candidate, ?string $as): object
    {
        [$element, $sfc] = $candidate;
        $class = $as ?? ElementMatch::class;

        return new $class($element, $sfc);
    }

    protected function matchClass(): string
    {
        return ElementMatch::class;
    }
}
