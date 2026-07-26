<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Scribes\Frontend;

use JesseGall\CodeCommandments\Detectors\Frontend\SwitchCaseChain;
use JesseGall\CodeCommandments\Scribes\RepentScribe;
use JesseGall\CodeCommandments\Vue\Directive;
use JesseGall\CodeCommandments\Vue\ElementMatch;

/**
 * Repents v-if/else-if/v-else chains by rewriting to `<SwitchCase :value>` component
 * with a named slot per case. Gathers branches from the head via {@see SwitchCaseChain::at},
 * preserves markup/formatting, strips structural directives.
 */
final class SwitchCaseScribe extends RepentScribe
{
    /**
     * @param  list<ElementMatch>  $findings  the `v-if` heads the detector flagged
     */
    public function rewrite(array $findings): array
    {
        return $this->draft($findings)
            ->map(static fn (ElementMatch $head): ?SwitchCaseChain => SwitchCaseChain::at($head))
            ->replace(fn (SwitchCaseChain $chain) => $this->switch($chain))
            ->rewrites();
    }

    /**
     * The `<SwitchCase>` block a chain becomes — subject once, a named slot per case.
     */
    private function switch(SwitchCaseChain $chain): string
    {
        $span = $chain->span();
        $indent = $span->lineIndent();
        $slots = [];

        foreach ($chain->branches as $index => $branch) {
            $element = $branch['element'];
            $directive = match (true) {
                $index === 0 => Directive::If,
                $branch['key'] === null => Directive::Else,
                default => Directive::ElseIf,
            };
            $name = $branch['key'] ?? 'default';
            // The branch source with its structural directive spliced out by its KNOWN span.
            $stripped = $element->sourceOmitting($span->source, $element->start, $element->end, [$directive]);

            // A branch that is already a <template> becomes the slot itself (don't nest a
            // second <template>): write the slot name in right after the tag — at the tag's
            // own length, not by re-scanning for `<template`. Any other element is wrapped.
            $slots[] = $element->isTemplate()
                ? "{$indent}    " . self::withSlot($stripped, $element->tag, $name)
                : "{$indent}    <template #{$name}>{$stripped}</template>";
        }

        return "<SwitchCase :value=\"{$chain->subject}\">\n"
            . implode("\n", $slots)
            . "\n{$indent}</SwitchCase>";
    }

    /**
     * Insert a `#slot` name into a `<template>` opening tag, right after the tag name.
     */
    private static function withSlot(string $templateSource, string $tag, string $slot): string
    {
        $afterTag = strlen('<' . $tag);

        return substr($templateSource, 0, $afterTag) . " #{$slot}" . substr($templateSource, $afterTag);
    }
}
