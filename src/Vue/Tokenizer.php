<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue;

/**
 * Turns the inner HTML of a Vue `<template>` into a tree of {@see Element} nodes.
 *
 * A hand-written, forgiving scanner — not a spec HTML5 parser: it tracks line
 * numbers (so a finding can point at `file:line`), keeps Vue's directive
 * attributes intact, honours quotes (so `>` inside `:x="a > b"` doesn't end a tag),
 * treats `{{ … }}`/text as text nodes, drops comments, and closes HTML void
 * elements and self-closing tags without a matching end tag. Mismatched or stray
 * end tags are tolerated rather than fatal.
 */
final class Tokenizer
{
    private const array VOID = [
        'area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input',
        'link', 'meta', 'param', 'source', 'track', 'wbr',
    ];

    /**
     * @param  int  $lineOffset  the source line the template content starts on, so
     *                           element lines map back to the `.vue` file
     */
    public function tokenize(string $html, int $lineOffset = 1): Element
    {
        /** @var list<array{tag: string, attributes: array<string, string|null>, line: int, children: list<Element>}> $stack */
        $stack = [['tag' => '#root', 'attributes' => [], 'line' => $lineOffset, 'children' => []]];

        $length = strlen($html);
        $pos = 0;

        while (true) {
            $lt = strpos($html, '<', $pos);
            $textEnd = $lt === false ? $length : $lt;

            $this->emitText($stack, substr($html, $pos, $textEnd - $pos), $pos, $html, $lineOffset);

            if ($lt === false) {
                break;
            }

            if (substr($html, $lt, 4) === '<!--') {
                $end = strpos($html, '-->', $lt);
                $pos = $end === false ? $length : $end + 3;

                continue;
            }

            if (substr($html, $lt, 2) === '</') {
                $pos = $this->closeTag($stack, $html, $lt);

                continue;
            }

            if (preg_match('/\G<([a-zA-Z][\w.\-:]*)/A', $html, $match, 0, $lt) !== 1) {
                // A stray '<' (e.g. inside an interpolation) — treat it as text.
                $this->emitText($stack, '<', $lt, $html, $lineOffset);
                $pos = $lt + 1;

                continue;
            }

            $pos = $this->openTag($stack, $html, $lt, $match[1], $lineOffset);
        }

        while (count($stack) > 1) {
            $this->fold($stack);
        }

        $root = new Element('#root', [], $stack[0]['children'], $lineOffset);

        foreach ($root->children as $child) {
            $child->parent = $root;
        }

        return $root;
    }

    /**
     * @param  list<array{tag: string, attributes: array<string, string|null>, line: int, children: list<Element>}>  $stack
     */
    private function openTag(array &$stack, string $html, int $lt, string $tag, int $lineOffset): int
    {
        $length = strlen($html);
        $i = $lt + 1 + strlen($tag);
        $quote = null;

        while ($i < $length) {
            $char = $html[$i];

            if ($quote !== null) {
                if ($char === $quote) {
                    $quote = null;
                }
            } elseif ($char === '"' || $char === "'") {
                $quote = $char;
            } elseif ($char === '>') {
                break;
            }

            $i++;
        }

        $inner = substr($html, $lt + 1 + strlen($tag), $i - ($lt + 1 + strlen($tag)));
        $selfClosing = str_ends_with(rtrim($inner), '/');
        $attributes = Attributes::parse($selfClosing ? substr(rtrim($inner), 0, -1) : $inner);
        $line = substr_count($html, "\n", 0, $lt) + $lineOffset;

        if ($selfClosing || in_array(strtolower($tag), self::VOID, true)) {
            $stack[count($stack) - 1]['children'][] = new Element($tag, $attributes, [], $line);
        } else {
            $stack[] = ['tag' => $tag, 'attributes' => $attributes, 'line' => $line, 'children' => []];
        }

        return $i + 1;
    }

    /**
     * @param  list<array{tag: string, attributes: array<string, string|null>, line: int, children: list<Element>}>  $stack
     */
    private function closeTag(array &$stack, string $html, int $lt): int
    {
        $gt = strpos($html, '>', $lt);
        $end = $gt === false ? strlen($html) : $gt + 1;
        $tag = trim(substr($html, $lt + 2, ($gt === false ? strlen($html) : $gt) - ($lt + 2)));

        for ($depth = count($stack) - 1; $depth >= 1; $depth--) {
            if ($stack[$depth]['tag'] === $tag) {
                while (count($stack) - 1 >= $depth) {
                    $this->fold($stack);
                }

                break;
            }
        }

        return $end;
    }

    /**
     * Pop the top frame, build its {@see Element}, wire its children's parent, and
     * append it to the frame below.
     *
     * @param  list<array{tag: string, attributes: array<string, string|null>, line: int, children: list<Element>}>  $stack
     */
    private function fold(array &$stack): void
    {
        $frame = array_pop($stack);
        $element = new Element($frame['tag'], $frame['attributes'], $frame['children'], $frame['line']);

        foreach ($element->children as $child) {
            $child->parent = $element;
        }

        $stack[count($stack) - 1]['children'][] = $element;
    }

    /**
     * @param  list<array{tag: string, attributes: array<string, string|null>, line: int, children: list<Element>}>  $stack
     */
    private function emitText(array &$stack, string $text, int $offset, string $html, int $lineOffset): void
    {
        if (trim($text) === '') {
            return;
        }

        $line = substr_count($html, "\n", 0, $offset) + $lineOffset;
        $stack[count($stack) - 1]['children'][] = new Element('#text', [], [], $line, $text);
    }
}
