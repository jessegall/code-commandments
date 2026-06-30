<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Scribes\Frontend;

use Closure;
use JesseGall\CodeCommandments\Scribes\RepentScribe;
use JesseGall\CodeCommandments\Scribes\Span;
use JesseGall\CodeCommandments\Vue\Directive;
use JesseGall\CodeCommandments\Vue\Element;
use JesseGall\CodeCommandments\Vue\ElementMatch;

/**
 * The one fix BOTH the duplicate-element and deep-data-reach detectors point at:
 * extract a chunk of template into its own component. They differ only in WHICH chunk
 * and WHAT it takes as props, so each detector hands this scribe back tuned for its
 * case — `ExtractComponentScribe::forDuplicates()` / `::forDeepReach()` — and the
 * runner feeds in that detector's findings.
 *
 *   - **Duplicates:** the repeated blocks `collapse` (by structure) to one component,
 *     its FREE variables as props, the markup lifted verbatim.
 *   - **Deep reach:** the reaching element becomes a component taking the MID-OBJECT
 *     as a prop (`order.customer.name` → prop `customer`), the chains rewritten
 *     relative so the child reaches one level, not three — flattening the reach.
 *
 * Each strategy is one fluent chain on the {@see \JesseGall\CodeCommandments\Scribes\Draft}
 * builder; re-indenting the lifted markup ({@see \JesseGall\CodeCommandments\Scribes\Span::reindent})
 * and keeping component names unique are the builder's job, so this scribe only
 * decides what to lift and what it takes as props.
 */
final class ExtractComponentScribe extends RepentScribe
{
    private const string DUPLICATES = 'duplicates';

    private const string DEEP_REACH = 'deep-reach';

    private function __construct(private readonly string $strategy) {}

    public static function forDuplicates(): self
    {
        return new self(self::DUPLICATES);
    }

    public static function forDeepReach(): self
    {
        return new self(self::DEEP_REACH);
    }

    /**
     * @param  list<ElementMatch>  $findings
     */
    public function rewrite(array $findings): array
    {
        return $this->strategy === self::DUPLICATES
            ? $this->duplicates($findings)
            : $this->deepReach($findings);
    }

    /**
     * @param  list<ElementMatch>  $findings
     */
    private function duplicates(array $findings): array
    {
        return $this->draft($findings)
            ->collapse(static fn (ElementMatch $block): string => $block->structureHash())
            ->create(fn (ElementMatch $block): array => $this->file($block, self::freeVariables($block), self::markup($block)))
            ->rewrites();
    }

    /**
     * @param  list<ElementMatch>  $findings
     */
    private function deepReach(array $findings): array
    {
        return $this->draft($findings)
            ->create(fn (ElementMatch $block): array => $this->reachComponent($block))
            ->rewrites();
    }

    /**
     * A deep-reach cluster's component: the boundary lifted, its shared object
     * flattened to a prop, and EVERY free variable the markup still reads passed in —
     * so the draft actually compiles, not just the mid-object.
     *
     * @return array{0: string, 1: string}  [path, content]
     */
    private function reachComponent(ElementMatch $block): array
    {
        [$prefix, $prop] = self::midObject($block);

        $markup = $prefix === []
            ? self::markup($block)
            : str_replace(implode('.', $prefix), $prop, self::markup($block));

        return $this->file($block, self::reachProps($block, $prefix, $prop), $markup);
    }

    /**
     * The props a deep-reach component needs: the block's free variables, with the
     * flattened object's root dropped ONLY when every reach through it went via the
     * mid-object (so `$prop` fully replaces it), and `$prop` itself added.
     *
     * @param  list<string>  $prefix
     * @return list<string>
     */
    private static function reachProps(ElementMatch $block, array $prefix, string $prop): array
    {
        $free = self::freeVariables($block);

        if ($prefix === []) {
            return $free;
        }

        $root = $prefix[0];
        $rootReadElsewhere = false;

        foreach (self::chains($block) as $chain) {
            if (($chain[0] ?? null) === $root && array_slice($chain, 0, count($prefix)) !== $prefix) {
                $rootReadElsewhere = true; // the root is still read shallowly — keep it as a prop too
                break;
            }
        }

        $props = $rootReadElsewhere ? $free : array_values(array_diff($free, [$root]));

        return in_array($prop, $props, true) ? $props : [...$props, $prop];
    }

    /**
     * The new component file a block becomes — its path beside the source, and a
     * `<script setup>` + `<template>` body.
     *
     * @param  list<string>  $props
     * @return array{0: string, 1: string}  [path, content]
     */
    private function file(ElementMatch $block, array $props, string $markup): array
    {
        $name = self::componentName($block, $props);

        return [$block->sibling("{$name}.vue"), self::render($props, $markup)];
    }

    /**
     * The block's markup re-indented for the new file — unwrapping a context-bound
     * `<template>` (a slot or `v-else`) to its CONTENT, since the wrapper can't be a
     * component root.
     */
    private static function markup(ElementMatch $block): string
    {
        return self::contentSpan($block)->reindent();
    }

    /**
     * The span to lift: the block itself, or — when the block is a context-bound
     * `<template>` — the span of its element children (its inner content).
     */
    private static function contentSpan(ElementMatch $block): Span
    {
        if (! $block->isContextBound()) {
            return $block->span();
        }

        $children = $block->elements();

        if ($children === []) {
            return $block->span();
        }

        $last = $children[count($children) - 1];

        return new Span($block->file(), $block->sfc->source, $children[0]->start, $last->end);
    }

    /**
     * @param  list<string>  $props
     */
    private static function render(array $props, string $markup): string
    {
        $defineProps = $props === []
            ? ''
            : 'defineProps<{ ' . implode('; ', array_map(static fn (string $p): string => "{$p}: unknown", $props)) . " }>();\n";

        return "<script setup lang=\"ts\">\n{$defineProps}</script>\n\n<template>\n{$markup}\n</template>\n";
    }

    // ---- deep-reach strategy --------------------------------------------------

    /**
     * The shared object a deep-reach cluster takes as a prop: the common prefix of its
     * deep chains, stopped one short of the leaf. `order.customer.name` +
     * `order.customer.email` → prefix `['order','customer']`, prop `customer`. Returns
     * an empty prefix (no flatten) when the block holds no deep chain.
     *
     * @return array{0: list<string>, 1: string}  [prefix, prop]
     */
    private static function midObject(ElementMatch $block): array
    {
        $deep = array_filter(self::chains($block), static fn (array $chain): bool => count($chain) >= 3);

        if ($deep === []) {
            return [[], ''];
        }

        $prefix = array_shift($deep);
        $shortest = count($prefix);

        foreach ($deep as $chain) {
            $prefix = self::commonPrefix($prefix, $chain);
            $shortest = min($shortest, count($chain));
        }

        // Stop one short of the leaf, so the prop is the OBJECT being read, not a field.
        $mid = array_slice($prefix, 0, min(count($prefix), $shortest - 1));

        return [$mid, $mid[count($mid) - 1] ?? ''];
    }

    /**
     * Every member chain across the block's expressions.
     *
     * @return list<list<string>>
     */
    private static function chains(ElementMatch $block): array
    {
        $chains = [];

        self::eachElement($block, static function (Element $element) use (&$chains): void {
            foreach ($element->expressions() as $expression) {
                $chains = array_merge($chains, $expression->chains());
            }
        });

        return $chains;
    }

    /**
     * @param  list<string>  $a
     * @param  list<string>  $b
     * @return list<string>
     */
    private static function commonPrefix(array $a, array $b): array
    {
        $prefix = [];

        foreach ($a as $i => $segment) {
            if (($b[$i] ?? null) !== $segment) {
                break;
            }

            $prefix[] = $segment;
        }

        return $prefix;
    }

    // ---- props + naming -------------------------------------------------------

    /**
     * The free variables the block reads (expression roots) minus the ones it binds
     * itself (`v-for` items) — the props the extracted component needs.
     *
     * @return list<string>
     */
    private static function freeVariables(ElementMatch $block): array
    {
        $reads = [];
        $bound = [];
        $called = [];

        self::eachElement($block, static function (Element $element) use (&$reads, &$bound, &$called): void {
            foreach (self::loopVars($element->attribute(Directive::For)) as $var) {
                $bound[] = $var;
            }

            foreach ($element->expressions() as $expression) {
                $reads = array_merge($reads, $expression->roots());
                $called = array_merge($called, $expression->calledFunctions());
            }
        });

        // `$event`, `$slots`, … are template-provided globals, never props.
        $reads = array_filter($reads, static fn (string $root): bool => ! str_starts_with($root, '$'));

        return array_values(array_diff(array_unique($reads), $bound, $called));
    }

    /**
     * @return list<string>
     */
    private static function loopVars(?string $expression): array
    {
        if ($expression === null) {
            return [];
        }

        $separator = str_contains($expression, ' in ') ? ' in ' : ' of ';
        $left = trim(strstr($expression, $separator, true) ?: '', " ()\t");

        return array_values(array_filter(array_map(trim(...), explode(',', $left))));
    }

    /**
     * @param  list<string>  $props
     */
    private static function componentName(ElementMatch $block, array $props): string
    {
        if ($block->tag !== strtolower($block->tag)) {
            return $block->tag . 'Group';
        }

        return $props === [] ? 'ExtractedSection' : ucfirst($props[0]) . 'Section';
    }

    /**
     * @param  Closure(Element): void  $visit
     */
    private static function eachElement(Element $node, Closure $visit): void
    {
        if ($node->isElement()) {
            $visit($node);
        }

        foreach ($node->children as $child) {
            self::eachElement($child, $visit);
        }
    }
}
