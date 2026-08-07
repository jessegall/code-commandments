<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Scribes\Frontend;

use JesseGall\CodeCommandments\Scribes\RepentScribe;
use JesseGall\CodeCommandments\Vue\Attribute;
use JesseGall\CodeCommandments\Vue\ElementMatch;

/**
 * Lifts control-flow directives off elements onto `<template>` wrappers. Moves structure to template,
 * keeps content on element; `v-for` carries its `:key`.
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
            $carried = $element->carriedDirectives();
            $names = array_map(static fn (Attribute $attribute): string => $attribute->name, $carried);
            // The element's source with the carried directives spliced out by their KNOWN
            // spans (the AST write engine), then nested one level into the new <template>.
            $inner = $this->indentInner($element->sourceOmitting($span->source, $span->start, $span->end, $names));
            $indent = $span->lineIndent();

            $rendered = Attribute::renderAll($carried);

            $draft->edit($span, "<template {$rendered}>\n{$indent}  {$inner}\n{$indent}</template>");
        }

        return $draft->rewrites();
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
