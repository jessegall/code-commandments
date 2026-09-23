<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments;

/**
 * A source file one of the tool's own parsers read into statements — a TypeScript module or a Python
 * one — as much of it as a reader of marked declarations needs: where it is, what it says, the language
 * its code is in, and the spans of its nodes.
 */
interface ParsedModule
{
    public string $file { get; }

    public string $source { get; }

    public function language(): Language;

    /**
     * The `[start, end)` span of every node, parents before their children.
     *
     * @return list<array{0: int, 1: int}>
     */
    public function nodeSpans(): array;

    public function lineAt(int $offset): int;

    public function spanAt(int $start, int $end): Span;
}
