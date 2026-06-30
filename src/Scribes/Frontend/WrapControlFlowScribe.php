<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Scribes\Frontend;

use JesseGall\CodeCommandments\Scribes\RepentScribe;
use JesseGall\CodeCommandments\Vue\Directive;
use JesseGall\CodeCommandments\Vue\ElementMatch;

/**
 * Repents the {@see \JesseGall\CodeCommandments\Detectors\Frontend\ControlFlowOnElementDetector}
 * sin: lifts a control-flow directive off a real element onto a `<template>` wrapper.
 * `<div v-if="x" class="y">…</div>` becomes `<template v-if="x"><div class="y">…</div></template>`
 * — the structure moves to the template, the element stays pure content. A `v-for`
 * takes its `:key` with it (the key belongs on the `v-for`'d node).
 *
 * The element keeps every other attribute and its children; only the structural
 * directives are spliced out of its opening tag (last-first so offsets stay valid).
 */
final class WrapControlFlowScribe extends RepentScribe
{
    /**
     * @param  list<ElementMatch>  $findings
     */
    public function rewrite(array $findings): array
    {
        $draft = $this->draft([]);

        foreach ($findings as $element) {
            $span = $element->span();
            $carried = $this->carried($element);
            // The element's source with the carried directives spliced out by their KNOWN
            // spans (the AST write engine), then nested one level into the new <template>.
            $inner = $this->indentInner($element->sourceOmitting($span->source, $span->start, $span->end, array_keys($carried)));
            $indent = $span->lineIndent();

            $draft->edit($span, "<template {$this->attributes($carried)}>\n{$indent}  {$inner}\n{$indent}</template>");
        }

        return $draft->rewrites();
    }

    /**
     * The directives to move onto the `<template>`: the structural ones present, and a
     * `v-for`'s `:key`.
     *
     * @return array<string, string|null>
     */
    private function carried(ElementMatch $element): array
    {
        $carried = [];

        foreach (Directive::structural() as $directive) {
            if ($element->hasAttribute($directive)) {
                $carried[$directive->value] = $element->attribute($directive);
            }
        }

        foreach (isset($carried[Directive::For->value]) ? [':key', 'key'] : [] as $key) {
            if ($element->hasAttribute($key)) {
                $carried[$key] = $element->attribute($key);

                break;
            }
        }

        return $carried;
    }

    /**
     * @param  array<string, string|null>  $carried
     */
    private function attributes(array $carried): string
    {
        $attributes = [];

        foreach ($carried as $name => $value) {
            $attributes[] = $value === null ? $name : "{$name}=\"{$value}\"";
        }

        return implode(' ', $attributes);
    }

    /**
     * Indent the continuation lines of the lifted element one level deeper, so it nests
     * cleanly inside the new `<template>`.
     */
    private function indentInner(string $inner): string
    {
        $lines = explode("\n", $inner);

        return $lines[0] . implode('', array_map(static fn (string $line): string => $line === '' ? "\n" : "\n  {$line}", array_slice($lines, 1)));
    }
}
