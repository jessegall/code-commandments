<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Scribes\Frontend;

use JesseGall\CodeCommandments\Detectors\Frontend\SwitchCaseChain;
use JesseGall\CodeCommandments\Scribes\RepentScribe;
use JesseGall\CodeCommandments\Vue\Directive;
use JesseGall\CodeCommandments\Vue\ElementMatch;

/**
 * Repents the {@see \JesseGall\CodeCommandments\Detectors\Frontend\SwitchCaseDetector}
 * sin: rewrites a `v-if` / `v-else-if` [/ `v-else`] dispatch chain into the published
 * `<SwitchCase :value>` component with a slot per case (`v-else` → `#default`).
 *
 * The detector already found the chain HEADS; this scribe starts from each one,
 * gathers its branches off that head ({@see SwitchCaseChain::at}), and emends the
 * head→tail span with the `<SwitchCase>` block — every branch keeps its own markup
 * and formatting, only the structural directive is stripped. End-first application
 * and nested-chain skipping are the {@see \JesseGall\CodeCommandments\Scribes\Draft}'s
 * job, so the scribe just describes each replacement.
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
            ->replace(fn (SwitchCaseChain $chain): string => $this->switch($chain))
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
