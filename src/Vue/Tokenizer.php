<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue;

/**
 * Turns the inner HTML of a Vue `<template>` into a tree of {@see Element} nodes.
 *
 * A hand-written, forgiving scanner — not a spec HTML5 parser: it tracks line
 * numbers AND byte spans (so findings point at `file:line` and a {@see
 * \JesseGall\CodeCommandments\Scribes\Frontend\SwitchCaseScribe} can splice the
 * source), keeps Vue's directive attributes intact, honours quotes (so `>` inside
 * `:x="a > b"` doesn't end a tag), treats `{{ … }}`/text as text nodes, drops
 * comments, and closes HTML void elements and self-closing tags without a matching
 * end tag. Mismatched or stray end tags are tolerated rather than fatal.
 *
 * Spans are reported in the coordinates of the SFC source — pass the byte offset
 * the template content starts at.
 */
final class Tokenizer
{
    private const array VOID = [
        'area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input',
        'link', 'meta', 'param', 'source', 'track', 'wbr',
    ];

    private string $html = '';

    private int $lineOffset = 1;

    private int $byteOffset = 0;

    /** @var list<array{tag: string, attributes: array<string, string|null>, line: int, start: int, children: list<Element>}> */
    private array $stack = [];

    /**
     * @param  int  $lineOffset  the source line the template content starts on
     * @param  int  $byteOffset  the source byte offset the template content starts at
     */
    public function tokenize(string $html, int $lineOffset = 1, int $byteOffset = 0): Element
    {
        $this->html = $html;
        $this->lineOffset = $lineOffset;
        $this->byteOffset = $byteOffset;
        $this->stack = [$this->frame('#root', [], $lineOffset, $byteOffset, [])];

        $length = strlen($html);
        $pos = 0;

        while (true) {
            $lt = strpos($html, '<', $pos);
            $textEnd = $lt === false ? $length : $lt;

            $this->emitText(substr($html, $pos, $textEnd - $pos), $pos);

            if ($lt === false) {
                break;
            }

            if (substr($html, $lt, 4) === '<!--') {
                $end = strpos($html, '-->', $lt);
                $textEnd = $end === false ? $length : $end;
                $close = $end === false ? $length : $end + 3;

                $this->append(new Element(
                    '#comment',
                    [],
                    [],
                    substr_count($html, "\n", 0, $lt) + $this->lineOffset,
                    trim(substr($html, $lt + 4, $textEnd - ($lt + 4))),
                    $lt + $this->byteOffset,
                    $close + $this->byteOffset,
                ));

                $pos = $close;

                continue;
            }

            if (substr($html, $lt, 2) === '</') {
                $pos = $this->closeTag($lt);

                continue;
            }

            if (preg_match('/\G<([a-zA-Z][\w.\-:]*)/A', $html, $match, 0, $lt) !== 1) {
                $this->emitText('<', $lt);
                $pos = $lt + 1;

                continue;
            }

            $pos = $this->openTag($lt, $match[1]);
        }

        while (count($this->stack) > 1) {
            $this->fold($length);
        }

        $root = new Element('#root', [], $this->stack[0]['children'], $this->lineOffset, '', $this->byteOffset, $length + $this->byteOffset);

        foreach ($root->children as $child) {
            $child->parent = $root;
        }

        return $root;
    }

    private function openTag(int $lt, string $tag): int
    {
        $length = strlen($this->html);
        $i = $lt + 1 + strlen($tag);
        $quote = null;

        while ($i < $length) {
            $char = $this->html[$i];

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

        $inner = substr($this->html, $lt + 1 + strlen($tag), $i - ($lt + 1 + strlen($tag)));
        $selfClosing = str_ends_with(rtrim($inner), '/');
        $attributes = Attributes::parse($selfClosing ? substr(rtrim($inner), 0, -1) : $inner);
        $spans = $this->spansOf($inner, $lt + 1 + strlen($tag) + $this->byteOffset);
        $line = substr_count($this->html, "\n", 0, $lt) + $this->lineOffset;
        $start = $lt + $this->byteOffset;

        if ($selfClosing || in_array(strtolower($tag), self::VOID, true)) {
            $this->append(new Element($tag, $attributes, [], $line, '', $start, $i + 1 + $this->byteOffset, $spans));
        } else {
            $this->stack[] = $this->frame($tag, $attributes, $line, $start, $spans);
        }

        return $i + 1;
    }

    /**
     * The absolute `[start, end)` source span of every attribute on a tag, keyed by name —
     * the lexer's spans (relative to the attribute text) shifted to where the text sits.
     *
     * @return array<string, array{int, int}>
     */
    private function spansOf(string $inner, int $innerOffset): array
    {
        $spans = [];

        foreach (Attributes::scan($inner) as $attribute) {
            $spans[$attribute['name']] = [$innerOffset + $attribute['start'], $innerOffset + $attribute['end']];
        }

        return $spans;
    }

    private function closeTag(int $lt): int
    {
        $gt = strpos($this->html, '>', $lt);
        $end = $gt === false ? strlen($this->html) : $gt + 1;
        $tag = trim(substr($this->html, $lt + 2, ($gt === false ? strlen($this->html) : $gt) - ($lt + 2)));

        for ($depth = count($this->stack) - 1; $depth >= 1; $depth--) {
            if ($this->stack[$depth]['tag'] === $tag) {
                while (count($this->stack) - 1 >= $depth) {
                    $this->fold($end);
                }

                break;
            }
        }

        return $end;
    }

    /**
     * Pop the top frame, build its {@see Element} (closing at $endInHtml), wire its
     * children's parent, and append it to the frame below.
     */
    private function fold(int $endInHtml): void
    {
        $frame = array_pop($this->stack);
        $element = new Element(
            $frame['tag'],
            $frame['attributes'],
            $frame['children'],
            $frame['line'],
            '',
            $frame['start'],
            $endInHtml + $this->byteOffset,
            $frame['attributeSpans'],
        );

        foreach ($element->children as $child) {
            $child->parent = $element;
        }

        $this->append($element);
    }

    private function emitText(string $text, int $offset): void
    {
        if (trim($text) === '') {
            return;
        }

        $line = substr_count($this->html, "\n", 0, $offset) + $this->lineOffset;
        $start = $offset + $this->byteOffset;
        $this->append(new Element('#text', [], [], $line, $text, $start, $start + strlen($text)));
    }

    private function append(Element $element): void
    {
        $this->stack[count($this->stack) - 1]['children'][] = $element;
    }

    /**
     * @param  array<string, array{int, int}>  $attributeSpans
     * @return array{tag: string, attributes: array<string, string|null>, line: int, start: int, children: list<Element>, attributeSpans: array<string, array{int, int}>}
     */
    private function frame(string $tag, array $attributes, int $line, int $start, array $attributeSpans): array
    {
        return ['tag' => $tag, 'attributes' => $attributes, 'line' => $line, 'start' => $start, 'children' => [], 'attributeSpans' => $attributeSpans];
    }
}
