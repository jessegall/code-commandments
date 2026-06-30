<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Rewriting\Frontend;

use JesseGall\CodeCommandments\Detectors\Frontend\SwitchCaseChain;
use JesseGall\CodeCommandments\Vue\Codebase;
use JesseGall\CodeCommandments\Vue\Directive;
use JesseGall\CodeCommandments\Vue\Element;
use JesseGall\CodeCommandments\Vue\Sfc;

/**
 * Repents the {@see \JesseGall\CodeCommandments\Detectors\Frontend\SwitchCaseDetector}
 * sin: rewrites a `v-if` / `v-else-if` [/ `v-else`] dispatch chain into the published
 * `<SwitchCase :value>` component with a slot per case (`v-else` → `#default`).
 *
 * It splices the SFC source by byte span — so every branch keeps its own markup and
 * formatting, only the structural directive is stripped — and rewrites chains last-
 * first so earlier offsets stay valid.
 */
final class SwitchCaseScribe
{
    /**
     * @return array<string, string>  changed path => new source
     */
    public function rewrites(Codebase $components): array
    {
        $rewrites = [];

        foreach ($components->components() as $component) {
            $source = $this->repent($component);

            if ($source !== null && $source !== $component->source) {
                $rewrites[$component->path] = $source;
            }
        }

        return $rewrites;
    }

    /**
     * The component's source with every switch-shaped chain rewritten, or null when
     * there's nothing to fix.
     */
    public function repent(Sfc $component): ?string
    {
        $chains = [];

        foreach ($this->elements($component->template) as $element) {
            $chain = SwitchCaseChain::at($element);

            if ($chain !== null) {
                $chains[] = $chain;
            }
        }

        if ($chains === []) {
            return null;
        }

        // Rewrite last-first so an earlier chain's byte offsets stay valid.
        usort($chains, static fn (SwitchCaseChain $a, SwitchCaseChain $b): int => self::head($b)->start <=> self::head($a)->start);

        $source = $component->source;
        $consumed = strlen($source) + 1;

        foreach ($chains as $chain) {
            $end = self::tail($chain)->end;

            if ($end > $consumed) {
                continue; // overlaps a chain already rewritten (a nested chain); skip
            }

            $source = $this->splice($source, $chain);
            $consumed = self::head($chain)->start;
        }

        return $source;
    }

    private function splice(string $source, SwitchCaseChain $chain): string
    {
        $start = self::head($chain)->start;
        $end = self::tail($chain)->end;
        $indent = $this->indentAt($source, $start);

        $slots = [];

        foreach ($chain->branches as $index => $branch) {
            $element = $branch['element'];
            $directive = match (true) {
                $index === 0 => Directive::If,
                $branch['key'] === null => Directive::Else,
                default => Directive::ElseIf,
            };
            $name = $branch['key'] ?? 'default';
            $stripped = $this->withoutDirective(substr($source, $element->start, $element->end - $element->start), $directive);

            // A branch that is already a <template> becomes the slot itself (don't
            // nest a second <template>); any other element is wrapped in one.
            $slots[] = strtolower($element->tag) === 'template'
                ? "{$indent}    " . preg_replace('/^<template\b/', "<template #{$name}", $stripped, 1)
                : "{$indent}    <template #{$name}>{$stripped}</template>";
        }

        $replacement = "<SwitchCase :value=\"{$chain->subject}\">\n"
            . implode("\n", $slots)
            . "\n{$indent}</SwitchCase>";

        return substr($source, 0, $start) . $replacement . substr($source, $end);
    }

    private function withoutDirective(string $elementSource, string|Directive $directive): string
    {
        $pattern = '/\s+' . preg_quote(Directive::name($directive), '/') . '(?:\s*=\s*"[^"]*"|\s*=\s*\'[^\']*\')?/';

        return preg_replace($pattern, '', $elementSource, 1) ?? $elementSource;
    }

    /**
     * The indentation (leading whitespace) of the line $pos sits on, or '' when the
     * line has non-whitespace before it.
     */
    private function indentAt(string $source, int $pos): string
    {
        $lineStart = strrpos(substr($source, 0, $pos), "\n");
        $lineStart = $lineStart === false ? 0 : $lineStart + 1;
        $prefix = substr($source, $lineStart, $pos - $lineStart);

        return $prefix !== '' && trim($prefix) === '' ? $prefix : '';
    }

    /**
     * @return list<Element>
     */
    private function elements(Element $node): array
    {
        $elements = [];

        foreach ($node->children as $child) {
            if ($child->isElement()) {
                $elements[] = $child;
            }

            foreach ($this->elements($child) as $descendant) {
                $elements[] = $descendant;
            }
        }

        return $elements;
    }

    private static function head(SwitchCaseChain $chain): Element
    {
        return $chain->branches[0]['element'];
    }

    private static function tail(SwitchCaseChain $chain): Element
    {
        return $chain->branches[count($chain->branches) - 1]['element'];
    }
}
