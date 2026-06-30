<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Vue;

/**
 * A parsed Vue single-file component: its top-level blocks in document order, and
 * the tokenized `<template>` tree.
 *
 * Block splitting is deliberately separate from template tokenizing — `<script>`
 * and `<style>` hold JS/TS and CSS, not HTML, so their bodies are read raw (up to
 * the matching end tag); only the template is parsed into {@see Element}s. The
 * outer `<template>` is found depth-aware, so nested `<template v-if>` inside it
 * doesn't end the block early.
 */
final class Sfc
{
    private const array BLOCK_TAGS = ['script', 'template', 'style'];

    /**
     * @param  list<Block>  $blocks  in document order
     */
    private function __construct(
        public readonly string $path,
        public readonly string $source,
        public readonly array $blocks,
        public readonly Element $template,
    ) {}

    public static function parse(string $source, string $path = 'component.vue'): self
    {
        $blocks = [];
        $template = null;
        $length = strlen($source);
        $i = 0;

        while ($i < $length) {
            $lt = strpos($source, '<', $i);

            if ($lt === false) {
                break;
            }

            if (substr($source, $lt, 4) === '<!--') {
                $end = strpos($source, '-->', $lt);
                $i = $end === false ? $length : $end + 3;

                continue;
            }

            if (preg_match('/\G<(script|template|style)\b([^>]*?)(\/?)>/iA', $source, $match, 0, $lt) !== 1) {
                $i = $lt + 1;

                continue;
            }

            $tag = strtolower($match[1]);
            $openEnd = $lt + strlen($match[0]);
            $line = substr_count($source, "\n", 0, $lt) + 1;

            if ($match[3] === '/') {
                $blocks[] = new Block($tag, Attributes::parse($match[2]), '', $line);
                $i = $openEnd;

                continue;
            }

            [$content, $i] = $tag === 'template'
                ? self::readTemplate($source, $openEnd)
                : self::readRaw($source, $openEnd, $tag);

            $blocks[] = new Block($tag, Attributes::parse($match[2]), $content, $line);

            if ($tag === 'template' && $template === null) {
                $template = new Tokenizer()->tokenize($content, substr_count($source, "\n", 0, $openEnd) + 1, $openEnd);
            }
        }

        return new self($path, $source, $blocks, $template ?? new Element('#root', [], [], 1));
    }

    /**
     * How many lines the `<template>` block spans — a proxy for "is this component
     * big enough that a deep data reach is worth extracting".
     */
    public function templateLineCount(): int
    {
        $block = $this->block('template');

        return $block === null ? 0 : substr_count($block->content, "\n") + 1;
    }

    /**
     * The first block of a tag, or null.
     */
    public function block(string $tag): ?Block
    {
        foreach ($this->blocks as $block) {
            if ($block->tag === $tag) {
                return $block;
            }
        }

        return null;
    }

    /**
     * The block tags in document order — e.g. ['template', 'script'] reveals a
     * script-after-template sin.
     *
     * @return list<string>
     */
    public function order(): array
    {
        return array_map(static fn (Block $block): string => $block->tag, $this->blocks);
    }

    /**
     * Read a raw (non-HTML) block body up to its matching end tag.
     *
     * @return array{0: string, 1: int}  content and the position past `</tag>`
     */
    private static function readRaw(string $source, int $from, string $tag): array
    {
        $close = stripos($source, "</{$tag}", $from);

        if ($close === false) {
            return [substr($source, $from), strlen($source)];
        }

        $gt = strpos($source, '>', $close);

        return [substr($source, $from, $close - $from), $gt === false ? strlen($source) : $gt + 1];
    }

    /**
     * Read the outer `<template>` body, counting nested `<template>`s so an inner
     * `<template v-if>` doesn't close the block early.
     *
     * @return array{0: string, 1: int}
     */
    private static function readTemplate(string $source, int $from): array
    {
        $length = strlen($source);
        $depth = 1;
        $i = $from;

        while ($i < $length) {
            $lt = strpos($source, '<', $i);

            if ($lt === false) {
                break;
            }

            if (preg_match('/\G<(\/?)template\b/iA', $source, $match, 0, $lt) === 1) {
                $depth += $match[1] === '/' ? -1 : 1;

                if ($depth === 0) {
                    $gt = strpos($source, '>', $lt);

                    return [substr($source, $from, $lt - $from), $gt === false ? $length : $gt + 1];
                }
            }

            $i = $lt + 1;
        }

        return [substr($source, $from), $length];
    }
}
